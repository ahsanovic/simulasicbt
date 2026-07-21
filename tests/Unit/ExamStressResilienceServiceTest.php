<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
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
use App\Services\ExamStressResilienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamStressResilienceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_insufficient_when_exam_ended_before_stress_window(): void
    {
        [$attempt] = $this->createSubmittedAttemptWithDurations([
            '1' => 30,
            '2' => 30,
            '3' => 30,
        ], [
            1 => true,
            2 => true,
            3 => true,
        ], durationMinutes: 100, submittedEarly: true);

        $analysis = app(ExamStressResilienceService::class)->analyzeAttempt($attempt);

        $this->assertFalse($analysis['has_data']);
        $this->assertTrue($analysis['insufficient']);
        $this->assertSame('early_exit', $analysis['reason']);
        $this->assertStringContainsString('zona stres', $analysis['insight']);
    }

    public function test_returns_insufficient_when_too_few_questions_answered(): void
    {
        [$attempt] = $this->createSubmittedAttemptWithDurations([
            '1' => 30,
            '2' => 30,
        ], [
            1 => true,
            2 => false,
        ], durationMinutes: 20, submittedEarly: false);

        $analysis = app(ExamStressResilienceService::class)->analyzeAttempt($attempt);

        $this->assertFalse($analysis['has_data']);
        $this->assertTrue($analysis['insufficient']);
        $this->assertSame('too_few_answers', $analysis['reason']);
    }

    public function test_analyzes_accuracy_drop_in_stress_window(): void
    {
        [$attempt] = $this->createSubmittedAttemptWithDurations([
            '1' => 100,
            '2' => 100,
            '3' => 500,
            '4' => 500,
            '5' => 500,
        ], [
            1 => true,
            2 => true,
            3 => false,
            4 => false,
            5 => false,
        ]);

        $analysis = app(ExamStressResilienceService::class)->analyzeAttempt($attempt, [
            'red_zone_triggers' => 2,
            'red_zone_questions' => [3, 4],
        ]);

        $this->assertTrue($analysis['has_data']);
        $this->assertSame(100.0, $analysis['baseline_accuracy']);
        $this->assertSame(0.0, $analysis['stress_accuracy']);
        $this->assertSame(100.0, $analysis['accuracy_drop']);
        $this->assertSame(2, $analysis['red_zone_triggers']);
        $this->assertSame('rendah', $analysis['level']);
    }

    /**
     * @param  array<string, int>  $durations
     * @param  array<int, bool>  $correctBySortOrder
     * @return array{0: ExamAttempt}
     */
    private function createSubmittedAttemptWithDurations(
        array $durations,
        array $correctBySortOrder,
        int $durationMinutes = 20,
        bool $submittedEarly = false,
    ): array {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Stress Test Exam',
            'slug' => 'stress-test-exam-'.uniqid(),
            'duration_minutes' => $durationMinutes,
            'status' => ExamStatus::Published,
            'created_by' => $admin->id,
        ]);

        $startedAt = now()->subMinutes($durationMinutes);
        $expiresAt = $startedAt->copy()->addMinutes($durationMinutes);
        $submittedAt = $submittedEarly
            ? $startedAt->copy()->addMinutes(3)
            : $expiresAt->copy()->subSeconds(30);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => $startedAt,
            'submitted_at' => $submittedAt,
            'expires_at' => $expiresAt,
            'status' => ExamAttemptStatus::Submitted,
            'stress_test_enabled' => true,
            'question_duration' => ['by_sort_order' => $durations],
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk-stress',
            'sort_order' => 1,
        ]);

        foreach ($correctBySortOrder as $sortOrder => $isCorrect) {
            $material = Material::query()->create([
                'subject_id' => $subject->id,
                'slug' => 'materi-'.$sortOrder,
                'name' => 'Materi '.$sortOrder,
                'sort_order' => 1,
            ]);

            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => 'Soal '.$sortOrder,
                'difficulty' => 'medium',
                'is_active' => true,
            ]);

            $correctOption = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => 'Benar',
                'is_correct' => true,
                'sort_order' => 1,
            ]);

            $wrongOption = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'B',
                'content' => 'Salah',
                'is_correct' => false,
                'sort_order' => 2,
            ]);

            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => $sortOrder,
                'selected_option_id' => $isCorrect ? $correctOption->id : $wrongOption->id,
            ]);
        }

        return [$attempt->fresh(['answers.question', 'answers.selectedOption', 'exam'])];
    }
}
