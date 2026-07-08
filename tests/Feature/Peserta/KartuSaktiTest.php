<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\FlashcardSourceType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ExamReview;
use App\Livewire\Peserta\KartuSakti;
use App\Livewire\Peserta\MateriBelajarShow;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\MaterialCheatSheet;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\FlashcardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KartuSaktiTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_kartu_sakti_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.kartu-sakti.index'))
            ->assertOk()
            ->assertSee('Kartu Sakti');
    }

    public function test_can_save_wrong_question_from_exam_review(): void
    {
        [$user, $attempt, $wrongQuestion] = $this->createAttemptWithWrongAnswer();

        Livewire::actingAs($user)
            ->test(ExamReview::class, ['attempt' => $attempt])
            ->call('saveCurrentToFlashcard')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('flashcards', [
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question->value,
            'source_id' => $wrongQuestion->id,
        ]);
    }

    public function test_can_save_cheat_sheet_from_materi_belajar(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);
        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'integritas',
            'name' => 'Integritas',
            'sort_order' => 1,
        ]);
        MaterialCheatSheet::query()->create([
            'material_id' => $material->id,
            'content' => "## Konsep\n\nIntegritas penting.",
            'status' => MaterialCheatSheet::STATUS_COMPLETED,
            'generated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(MateriBelajarShow::class, [
                'subjectCode' => 'twk',
                'materialSlug' => 'integritas',
            ])
            ->call('saveToFlashcard')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('flashcards', [
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::CheatSheet->value,
            'source_id' => $material->id,
        ]);
    }

    public function test_review_session_awards_xp_and_updates_schedule(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $card = Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => 99,
            'front' => 'Soal?',
            'back' => 'Jawaban.',
            'subject_code' => SubjectCode::Twk,
            'interval_days' => 1,
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now()->subHour(),
        ]);

        Livewire::actingAs($user)
            ->test(KartuSakti::class)
            ->call('startReview')
            ->call('revealAnswer')
            ->call('rateCard', 'remembered')
            ->assertSet('mode', 'finished');

        $this->assertDatabaseHas('flashcard_review_sessions', [
            'user_id' => $user->id,
            'card_count' => 1,
            'xp_earned' => 1,
        ]);

        $card->refresh();
        $this->assertSame(1, $card->repetition_count);
        $this->assertTrue($card->next_review_at->isFuture());
    }

    public function test_most_forgotten_stat_prioritizes_high_forget_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => 1,
            'front' => 'A',
            'back' => 'B',
            'subject_code' => SubjectCode::Twk,
            'forget_count' => 1,
            'next_review_at' => now(),
        ]);

        Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => 2,
            'front' => 'C',
            'back' => 'D',
            'subject_code' => SubjectCode::Tiu,
            'forget_count' => 5,
            'next_review_at' => now(),
        ]);

        $mostForgotten = app(FlashcardService::class)->mostForgotten($user)->first();

        $this->assertSame(5, $mostForgotten?->forget_count);
    }

    /**
     * @return array{0: User, 1: ExamAttempt, 2: Question}
     */
    private function createAttemptWithWrongAnswer(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi',
            'slug' => 'simulasi',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 0,
            'score_tiu' => 0,
            'score_tkp' => 0,
            'total_score' => 0,
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'sejarah',
            'name' => 'Sejarah',
            'sort_order' => 1,
        ]);

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => 'Siapa proklamator?',
            'explanation' => 'Soekarno-Hatta.',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $correct = QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'content' => 'Benar',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $wrong = QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'B',
            'content' => 'Salah',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        ExamAnswer::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'sort_order' => 1,
            'selected_option_id' => $wrong->id,
            'answered_at' => now(),
        ]);

        return [$user, $attempt->fresh(), $question];
    }
}
