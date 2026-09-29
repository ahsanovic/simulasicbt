<?php

namespace Tests\Feature\Admin;

use App\Enums\QuestionOptionContentType;
use App\Enums\UserRole;
use App\Livewire\Admin\JabatanSkb\SoalIndex;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SkbSoalIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_soal_skb_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertOk()
            ->assertSee($jabatan->name);
    }

    public function test_peserta_cannot_access_soal_skb_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);
        $jabatan = $this->createJabatan();

        $this->actingAs($peserta)
            ->get(route('admin.jabatan-skb.soal.index', $jabatan))
            ->assertForbidden();
    }

    public function test_admin_can_create_question_with_text_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->set('options', [
                ['label' => 'A', 'content_type' => 'text', 'content' => 'Pilihan A', 'image_path' => null, 'is_correct' => true],
                ['label' => 'B', 'content_type' => 'text', 'content' => 'Pilihan B', 'image_path' => null, 'is_correct' => false],
            ])
            ->set('correctOptionIndex', 0)
            ->call('save', '<p>Soal SKB baru</p>')
            ->assertHasNoErrors();

        $question = SkbQuestion::query()->where('jabatan_skb_id', $jabatan->id)->latest()->first();

        $this->assertNotNull($question);
        $this->assertSame('<p>Soal SKB baru</p>', $question->content);
        $this->assertTrue($question->options->firstWhere('label', 'A')->is_correct);
        $this->assertFalse($question->options->firstWhere('label', 'B')->is_correct);
    }

    public function test_admin_can_create_question_with_image_option(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->set('options', [
                ['label' => 'A', 'content_type' => 'image', 'content' => '', 'image_path' => null, 'is_correct' => true],
                ['label' => 'B', 'content_type' => 'text', 'content' => 'Pilihan B', 'image_path' => null, 'is_correct' => false],
            ])
            ->set('optionImages.0', UploadedFile::fake()->image('option-a.png'))
            ->set('correctOptionIndex', 0)
            ->call('save', '<p>Soal dengan gambar</p>')
            ->assertHasNoErrors();

        $question = SkbQuestion::query()->with('options')->latest()->first();
        $imageOption = $question->options->firstWhere('label', 'A');

        $this->assertSame(QuestionOptionContentType::Image, $imageOption->content_type);
        Storage::disk('public')->assertExists($imageOption->image_path);
    }

    public function test_admin_can_edit_question(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();
        $question = $this->createQuestion($jabatan);

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->call('openEditModal', $question->id)
            ->call('save', '<p>Soal diperbarui</p>')
            ->assertHasNoErrors();

        $this->assertSame('<p>Soal diperbarui</p>', $question->fresh()->content);
    }

    public function test_admin_can_delete_question(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = $this->createJabatan();
        $question = $this->createQuestion($jabatan);

        Livewire::actingAs($admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $jabatan])
            ->call('delete', $question->id);

        $this->assertSoftDeleted('skb_questions', ['id' => $question->id]);
    }

    private function createJabatan(): JabatanSkb
    {
        return JabatanSkb::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'is_active' => true,
        ]);
    }

    private function createQuestion(JabatanSkb $jabatan): SkbQuestion
    {
        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal awal</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        return $question;
    }
}
