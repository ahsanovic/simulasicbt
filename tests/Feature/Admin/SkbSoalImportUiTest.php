<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\JabatanSkb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkbSoalImportUiTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'content,explanation,difficulty,option_a,option_b,option_c,option_d,option_e,correct_option';

    public function test_soal_skb_page_shows_import_button(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis Kebijakan', 'slug' => 'analis-kebijakan', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertOk()
            ->assertSee('Import Soal');
    }

    public function test_failed_import_shows_error_modal_in_response(): void
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

        $followUp = $this->actingAs($admin)->get(route('admin.jabatan-skb.soal.index', $jabatan));

        $followUp->assertOk();
        $followUp->assertSee('Import Soal SKB Gagal', false);
        $followUp->assertSee('Opsi E', false);
        $followUp->assertSee('Pilihan jawaban wajib diisi.', false);
    }
}
