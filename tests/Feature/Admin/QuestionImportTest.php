<?php

namespace Tests\Feature\Admin;

use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Material;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'subject_code,material_slug,content,explanation,difficulty,option_a,option_b,option_c,option_d,option_e,correct_option,weight_a,weight_b,weight_c,weight_d,weight_e';

    public function test_failed_import_shows_error_modal_in_response(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            'subject_code,material_slug,content',
            'twk,slug-tidak-ada,Soal uji import',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Import Soal Gagal', false);
        $followUp->assertSee('Slug Materi', false);
        $followUp->assertSee('slug-tidak-ada', false);
    }

    public function test_twk_import_requires_all_option_columns(): void
    {
        $this->createImportSubjectsAndMaterials();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'twk,nasionalisme,Soal TWK,,,Pilihan A,Pilihan B,Pilihan C,Pilihan D,,a,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Opsi E', false);
        $followUp->assertSee('Pilihan jawaban wajib diisi.', false);
    }

    public function test_tiu_import_requires_all_option_columns(): void
    {
        $this->createImportSubjectsAndMaterials();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'tiu,analogi-verbal,Soal TIU,,,Pilihan A,Pilihan B,,Pilihan D,Pilihan E,b,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Opsi C', false);
        $followUp->assertSee('Pilihan jawaban wajib diisi.', false);
    }

    public function test_tkp_import_requires_all_weight_columns(): void
    {
        $this->createImportSubjectsAndMaterials();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'tkp,pelayanan-publik,Soal TKP,,,Sangat Setuju,Setuju,Netral,Tidak Setuju,Sangat Tidak Setuju,,5,4,3,2,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Bobot E', false);
        $followUp->assertSee('Bobot TKP wajib diisi.', false);
    }

    public function test_twk_import_requires_correct_option(): void
    {
        $this->createImportSubjectsAndMaterials();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'twk,nasionalisme,Soal TWK,,,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Jawaban Benar', false);
        $followUp->assertSee('Jawaban benar wajib diisi.', false);
    }

    public function test_tiu_import_requires_correct_option(): void
    {
        $this->createImportSubjectsAndMaterials();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'tiu,analogi-verbal,Soal TIU,,,Pilihan A,Pilihan B,Pilihan C,Pilihan D,Pilihan E,,,,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('soal.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.questions.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.questions.index'));
        $response->assertSessionHas('import_errors');

        $followUp = $this->actingAs($admin)->get(route('admin.questions.index'));

        $followUp->assertOk();
        $followUp->assertSee('Jawaban Benar', false);
        $followUp->assertSee('Jawaban benar wajib diisi.', false);
    }

    private function createImportSubjectsAndMaterials(): void
    {
        $twkSubject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $tiuSubject = Subject::query()->create([
            'code' => SubjectCode::Tiu,
            'name' => 'TIU',
            'slug' => 'tiu',
            'sort_order' => 2,
        ]);

        $tkpSubject = Subject::query()->create([
            'code' => SubjectCode::Tkp,
            'name' => 'TKP',
            'slug' => 'tkp',
            'sort_order' => 3,
        ]);

        Material::query()->create([
            'subject_id' => $twkSubject->id,
            'slug' => 'nasionalisme',
            'name' => 'Nasionalisme',
            'sort_order' => 1,
        ]);

        Material::query()->create([
            'subject_id' => $tiuSubject->id,
            'slug' => 'analogi-verbal',
            'name' => 'Analogi Verbal',
            'sort_order' => 1,
        ]);

        Material::query()->create([
            'subject_id' => $tkpSubject->id,
            'slug' => 'pelayanan-publik',
            'name' => 'Pelayanan Publik',
            'sort_order' => 1,
        ]);
    }
}
