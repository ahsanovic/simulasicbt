<?php

namespace Tests\Feature\Admin;

use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Admin\Questions\Index;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionImageOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_question_preserves_existing_option_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$subject, $material] = $this->createSubjectAndMaterial();

        $imagePath = 'question-options/existing.png';
        Storage::disk('public')->put($imagePath, 'fake-image');

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => '<p>Soal uji gambar</p>',
            'difficulty' => 'medium',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Image,
            'image_path' => $imagePath,
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openEditModal', $question->id)
            ->set('content', '<p>Soal uji gambar diperbarui</p>')
            ->call('save')
            ->assertHasNoErrors();

        $question->refresh()->load('options');

        $imageOption = $question->options->firstWhere('label', 'A');

        $this->assertSame(QuestionOptionContentType::Image, $imageOption->content_type);
        $this->assertSame($imagePath, $imageOption->image_path);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_create_question_stores_uploaded_option_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$subject, $material] = $this->createSubjectAndMaterial();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('subject_id', $subject->id)
            ->set('material_id', $material->id)
            ->set('content', '<p>Soal baru dengan gambar</p>')
            ->set('options', [
                ['label' => 'A', 'content_type' => 'image', 'content' => '', 'image_path' => null, 'is_correct' => true, 'score_weight' => 5],
                ['label' => 'B', 'content_type' => 'text', 'content' => 'Pilihan B', 'image_path' => null, 'is_correct' => false, 'score_weight' => 4],
            ])
            ->set('optionImages.0', UploadedFile::fake()->image('option-a.png'))
            ->call('save')
            ->assertHasNoErrors();

        $question = Question::query()->with('options')->latest()->first();

        $this->assertNotNull($question);
        $imageOption = $question->options->firstWhere('label', 'A');

        $this->assertSame(QuestionOptionContentType::Image, $imageOption->content_type);
        $this->assertNotNull($imageOption->image_path);
        Storage::disk('public')->assertExists($imageOption->image_path);
    }

    public function test_preview_modal_shows_tkp_option_scores(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Tkp,
            'name' => SubjectCode::Tkp->label(),
            'slug' => SubjectCode::Tkp->value,
            'sort_order' => 3,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'materi-tkp',
            'name' => 'Materi TKP',
            'sort_order' => 1,
        ]);

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => '<p>Soal TKP pratinjau</p>',
            'difficulty' => 'medium',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        foreach ([5, 4, 3, 2, 1] as $index => $score) {
            QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => chr(65 + $index),
                'content_type' => QuestionOptionContentType::Text,
                'content' => 'Pilihan '.chr(65 + $index),
                'is_correct' => false,
                'score_weight' => $score,
                'sort_order' => $index + 1,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openPreviewModal', $question->id)
            ->assertSee('Bobot 5')
            ->assertSee('Bobot 1')
            ->assertDontSee('Benar');
    }

    public function test_preview_modal_shows_question_content(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$subject, $material] = $this->createSubjectAndMaterial();

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => '<p>Konten soal pratinjau</p>',
            'difficulty' => 'medium',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Jawaban A',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Jawaban B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openPreviewModal', $question->id)
            ->assertSet('showPreviewModal', true)
            ->assertSee('Pratinjau Soal')
            ->assertSee('Konten soal pratinjau', false)
            ->assertSee('Jawaban A', false);
    }

    /**
     * @return array{0: Subject, 1: Material}
     */
    private function createSubjectAndMaterial(): array
    {
        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => SubjectCode::Twk->label(),
            'slug' => SubjectCode::Twk->value,
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'materi-twk',
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        return [$subject, $material];
    }
}
