<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\JabatanSkb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkbQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'content,explanation,difficulty,option_a,option_b,option_c,option_d,option_e,correct_option';

    public function test_admin_can_import_skb_questions_from_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis Kebijakan', 'slug' => 'analis-kebijakan', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB satu,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('skb_questions', [
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $this->assertDatabaseHas('skb_question_options', [
            'label' => 'A',
            'is_correct' => true,
        ]);
    }

    public function test_import_trims_trailing_whitespace_in_correct_option(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Arsiparis', 'slug' => 'arsiparis', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB spasi,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,"a "',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('skb_question_options', [
            'label' => 'A',
            'is_correct' => true,
        ]);
    }

    public function test_large_import_is_queued_and_processes_via_sync_queue(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, so a queued import job
        // runs immediately in-process during the test — no fake needed.
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Perawat', 'slug' => 'perawat', 'is_active' => true]);

        $rows = [self::CSV_HEADER];

        for ($i = 1; $i <= 101; $i++) {
            $rows[] = "Soal SKB nomor {$i},,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a";
        }

        $file = UploadedFile::fake()->createWithContent('soal-skb-besar.csv', implode("\n", $rows));

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('info');

        $this->assertDatabaseHas('skb_question_import_jobs', [
            'jabatan_skb_id' => $jabatan->id,
            'total_rows' => 101,
            'status' => 'completed',
        ]);

        $this->assertSame(101, $jabatan->questions()->count());
    }

    public function test_import_accepts_blank_difficulty_and_defaults_to_medium(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Bidan', 'slug' => 'bidan', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal tanpa kesulitan,,,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('skb_questions', [
            'jabatan_skb_id' => $jabatan->id,
            'difficulty' => 'medium',
        ]);
    }

    public function test_import_accepts_indonesian_difficulty_label(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Apoteker', 'slug' => 'apoteker', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal kesulitan indo,,Sulit,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('skb_questions', [
            'jabatan_skb_id' => $jabatan->id,
            'difficulty' => 'hard',
        ]);
    }

    public function test_import_rejects_unrecognized_difficulty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Guru', 'slug' => 'guru', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal kesulitan salah,,susahbanget,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('skb_questions', ['jabatan_skb_id' => $jabatan->id]);
    }

    public function test_import_requires_all_option_columns(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Auditor', 'slug' => 'auditor', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB kurang opsi,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,,a',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('skb_questions', ['jabatan_skb_id' => $jabatan->id]);
    }

    public function test_import_requires_correct_option(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Dokter', 'slug' => 'dokter', 'is_active' => true]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Soal SKB tanpa jawaban,,medium,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal-skb.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.jabatan-skb.soal.import', $jabatan), ['file' => $file]);

        $response->assertRedirect(route('admin.jabatan-skb.soal.index', $jabatan));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('skb_questions', ['jabatan_skb_id' => $jabatan->id]);
    }
}
