<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionImportTest extends TestCase
{
    use RefreshDatabase;

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
}
