<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Models\XpReward;
use App\Services\ExamQuestionGeneratorService;
use App\Services\ExamService;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RemedialExamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_remedial_attempt_creates_subset_of_wrong_answers(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $remedial = app(ExamService::class)->startRemedialAttempt($parent, $user);

        $this->assertTrue($remedial->isRemedial());
        $this->assertSame($parent->id, $remedial->parent_attempt_id);
        $this->assertSame(ExamAttemptType::Remedial, $remedial->attempt_type);
        $this->assertCount(2, $remedial->answers);
    }

    public function test_start_remedial_blocked_for_duel_attempt(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();
        $parent->update(['duel_session_id' => 1]);
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $this->expectException(ValidationException::class);

        app(ExamService::class)->startRemedialAttempt($parent->fresh(), $user);
    }

    public function test_remedial_duration_scales_with_wrong_count(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = Exam::query()->create([
            'title' => 'Simulasi Remedial',
            'slug' => 'simulasi-remedial-duration',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $service = app(ExamService::class);

        $this->assertSame(17, $service->remedialDurationMinutes($exam, 15, 110));
        $this->assertSame(3, $service->remedialDurationMinutes($exam, 2, 110));
    }

    public function test_start_remedial_requires_unlocked_xp(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();

        $this->expectException(ValidationException::class);

        app(ExamService::class)->startRemedialAttempt($parent, $user);
    }

    public function test_start_remedial_fails_when_no_wrong_answers(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers(allCorrect: true);
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $this->expectException(ValidationException::class);

        app(ExamService::class)->startRemedialAttempt($parent, $user);
    }

    public function test_submit_remedial_awards_xp_only_when_all_correct(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $service = app(ExamService::class);
        $remedial = $service->startRemedialAttempt($parent, $user);

        foreach ($remedial->answers as $answer) {
            $correctOption = QuestionOption::query()
                ->where('question_id', $answer->question_id)
                ->where('is_correct', true)
                ->first();

            $answer->update([
                'selected_option_id' => $correctOption?->id,
                'answered_at' => now(),
            ]);
        }

        $service->submitAttempt($remedial->fresh(), $user);

        $this->assertDatabaseHas('xp_rewards', [
            'user_id' => $user->id,
            'source_type' => 'remedial_perfect',
            'source_id' => $remedial->id,
            'amount' => GamificationService::REMEDIAL_PERFECT_XP_REWARD,
        ]);
    }

    public function test_submit_remedial_does_not_award_xp_when_not_all_correct(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $service = app(ExamService::class);
        $remedial = $service->startRemedialAttempt($parent, $user);

        $firstAnswer = $remedial->answers()->first();
        $wrongOption = QuestionOption::query()
            ->where('question_id', $firstAnswer->question_id)
            ->where('is_correct', false)
            ->first();

        $firstAnswer->update([
            'selected_option_id' => $wrongOption?->id,
            'answered_at' => now(),
        ]);

        $service->submitAttempt($remedial->fresh(), $user);

        $this->assertDatabaseMissing('xp_rewards', [
            'user_id' => $user->id,
            'source_type' => 'remedial_perfect',
            'source_id' => $remedial->id,
        ]);
    }

    public function test_crossing_remedial_unlock_threshold_sets_session_flash(): void
    {
        [$user, $parent] = $this->createParentAttemptWithMixedAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP - GamificationService::EXAM_FAIL_XP_REWARD);

        $parent->update([
            'status' => ExamAttemptStatus::InProgress,
            'submitted_at' => null,
        ]);

        app(ExamService::class)->submitAttempt($parent->fresh(['answers.selectedOption', 'answers.question.subject']), $user);

        $this->assertTrue(session('show_remedial_unlock_modal'));
    }

    /** @return array{0: User, 1: ExamAttempt} */
    private function createParentAttemptWithMixedAnswers(bool $allCorrect = false): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Remedial',
            'slug' => 'simulasi-remedial',
            'duration_minutes' => 110,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 10,
            'score_tiu' => 10,
            'score_tkp' => 10,
            'total_score' => 30,
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'twk-materi',
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => 'Soal '.$i,
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

            $isWrong = ! $allCorrect && $i <= 2;
            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => $i,
                'selected_option_id' => $isWrong ? $wrong->id : $correct->id,
                'answered_at' => now(),
            ]);
        }

        return [$user, $attempt->fresh(['exam', 'answers.question', 'answers.selectedOption'])];
    }

    private function grantXp(User $user, int $amount): void
    {
        XpReward::query()->create([
            'user_id' => $user->id,
            'source_type' => 'test_grant',
            'source_id' => $user->id,
            'amount' => $amount,
        ]);
    }
}
