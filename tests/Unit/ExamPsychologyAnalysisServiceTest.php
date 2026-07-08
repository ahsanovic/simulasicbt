<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamTelemetry;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamPsychologyAnalysisService;
use App\Services\ExamPsychologyTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamPsychologyAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_detects_panic_patterns(): void
    {
        [$attempt] = $this->createAttemptWithTelemetry();

        $analysis = app(ExamPsychologyAnalysisService::class)->aggregateForAttempt($attempt);

        $this->assertTrue($analysis['has_data']);
        $this->assertSame(2, $analysis['total_changes_in_panic_window']);
        $this->assertSame(1, $analysis['correct_to_wrong_in_panic_window']);
        $this->assertSame(1, $analysis['fast_skim_in_panic_window']);
        $this->assertNotEmpty($analysis['summary_lines']);
    }

    public function test_telemetry_service_marks_correct_to_wrong_changes(): void
    {
        [$attempt, $correctOption, $wrongOption] = $this->createAnswerableAttempt();

        $behavior = [
            '1' => [
                'first_option_id' => $correctOption->id,
                'change_count' => 1,
                'last_change_remaining_seconds' => 600,
            ],
        ];

        $attempt->answers()->first()->update(['selected_option_id' => $wrongOption->id]);

        app(ExamPsychologyTelemetryService::class)->persistForAttempt(
            $attempt->fresh(['answers.question.options', 'answers.selectedOption']),
            ['1' => 45],
            $behavior,
            600,
        );

        $telemetry = ExamTelemetry::query()->where('exam_attempt_id', $attempt->id)->first();

        $this->assertTrue($telemetry->is_changed_at_last_minute);
        $this->assertTrue($telemetry->changed_from_correct_to_wrong);
    }

    /**
     * @return array{0: ExamAttempt}
     */
    private function createAttemptWithTelemetry(): array
    {
        [$attempt] = $this->createAnswerableAttempt();

        ExamTelemetry::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_number' => 1,
            'time_spent_seconds' => 8,
            'is_changed_at_last_minute' => true,
            'changed_from_correct_to_wrong' => true,
            'remaining_time_seconds' => 600,
        ]);

        ExamTelemetry::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_number' => 2,
            'time_spent_seconds' => 90,
            'is_changed_at_last_minute' => true,
            'changed_from_correct_to_wrong' => false,
            'remaining_time_seconds' => 800,
        ]);

        return [$attempt->fresh()];
    }

    /**
     * @return array{0: ExamAttempt, 1: QuestionOption, 2: QuestionOption}
     */
    private function createAnswerableAttempt(): array
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
            'total_score' => 5,
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

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => 'Soal TWK',
            'difficulty' => 'easy',
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
            'sort_order' => 1,
            'selected_option_id' => $correctOption->id,
            'answered_at' => now(),
        ]);

        return [$attempt, $correctOption, $wrongOption];
    }
}
