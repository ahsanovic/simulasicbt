<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ExamReview;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_review_for_own_submitted_attempt(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithQuestions();

        $this->actingAs($user)
            ->get(route('peserta.exam.review', $attempt))
            ->assertOk()
            ->assertSee('Kunci Jawaban dan Pembahasan')
            ->assertSee('Pembahasan TWK')
            ->assertSee('Kunci Jawaban');
    }

    public function test_peserta_cannot_view_review_for_in_progress_attempt(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithQuestions();
        $attempt->update(['status' => ExamAttemptStatus::InProgress]);

        $this->actingAs($user)
            ->get(route('peserta.exam.review', $attempt))
            ->assertNotFound();
    }

    public function test_peserta_cannot_view_review_for_other_users_attempt(): void
    {
        [, $attempt] = $this->createSubmittedAttemptWithQuestions();
        $otherUser = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($otherUser)
            ->get(route('peserta.exam.review', $attempt))
            ->assertNotFound();
    }

    public function test_review_page_shows_participant_answer_and_explanation(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithQuestions();

        Livewire::actingAs($user)
            ->test(ExamReview::class, ['attempt' => $attempt])
            ->assertSee('Jawaban Anda')
            ->assertSee('Pembahasan TWK')
            ->assertSee('Benar');
    }

    public function test_history_page_shows_review_button_for_completed_attempts(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithQuestions();

        $this->actingAs($user)
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee('Kunci Jawaban dan Pembahasan')
            ->assertSee(route('peserta.exam.review', $attempt, false));
    }

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function createSubmittedAttemptWithQuestions(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd',
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
            'score_twk' => 5,
            'score_tiu' => 0,
            'score_tkp' => 5,
            'total_score' => 10,
        ]);

        $twkSubject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $tkpSubject = Subject::query()->create([
            'code' => SubjectCode::Tkp,
            'name' => 'TKP',
            'slug' => 'tkp',
            'sort_order' => 3,
        ]);

        $twkMaterial = Material::query()->create([
            'subject_id' => $twkSubject->id,
            'slug' => 'twk-materi',
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        $tkpMaterial = Material::query()->create([
            'subject_id' => $tkpSubject->id,
            'slug' => 'tkp-materi',
            'name' => 'Materi TKP',
            'sort_order' => 1,
        ]);

        $twkQuestion = Question::query()->create([
            'subject_id' => $twkSubject->id,
            'material_id' => $twkMaterial->id,
            'content' => 'Apa ibu kota Indonesia?',
            'explanation' => 'Pembahasan TWK: Jakarta adalah ibu kota Indonesia.',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $correctOption = QuestionOption::query()->create([
            'question_id' => $twkQuestion->id,
            'label' => 'A',
            'content' => 'Jakarta',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $twkQuestion->id,
            'label' => 'B',
            'content' => 'Bandung',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        ExamAnswer::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $twkQuestion->id,
            'sort_order' => 1,
            'selected_option_id' => $correctOption->id,
            'answered_at' => now(),
        ]);

        $tkpQuestion = Question::query()->create([
            'subject_id' => $tkpSubject->id,
            'material_id' => $tkpMaterial->id,
            'content' => 'Sikap kerja terbaik adalah?',
            'explanation' => null,
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $bestTkpOption = QuestionOption::query()->create([
            'question_id' => $tkpQuestion->id,
            'label' => 'A',
            'content' => 'Disiplin tinggi',
            'is_correct' => false,
            'score_weight' => 5,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $tkpQuestion->id,
            'label' => 'B',
            'content' => 'Kurang disiplin',
            'is_correct' => false,
            'score_weight' => 2,
            'sort_order' => 2,
        ]);

        ExamAnswer::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $tkpQuestion->id,
            'sort_order' => 2,
            'selected_option_id' => $bestTkpOption->id,
            'answered_at' => now(),
        ]);

        return [$user, $attempt->fresh()];
    }
}
