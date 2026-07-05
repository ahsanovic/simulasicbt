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
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamTimeManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTimeManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_attempt_returns_pillar_averages_and_longest_questions(): void
    {
        [$attempt] = $this->createAttemptWithDurations([
            '1' => 120,
            '2' => 90,
            '3' => 45,
        ]);

        $analysis = app(ExamTimeManagementService::class)->analyzeAttempt($attempt);

        $this->assertTrue($analysis['has_data']);
        $this->assertSame(120, $analysis['longest_questions'][0]['seconds']);
        $this->assertArrayHasKey('twk', $analysis['average_by_pillar']);
        $this->assertGreaterThan(0, $analysis['safe_seconds_per_question']);
    }

    public function test_analyze_user_time_patterns_aggregates_multiple_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        foreach ([['1' => 80], ['1' => 100]] as $durations) {
            [$attempt] = $this->createAttemptWithDurations($durations, $user);
            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
            ]);
        }

        $patterns = app(ExamTimeManagementService::class)->analyzeUserTimePatterns($user->id);

        $this->assertTrue($patterns['has_data']);
        $this->assertSame(2, $patterns['total_exams_with_data']);
        $this->assertSame(90, $patterns['average_seconds_by_pillar']['twk']);
    }

    /**
     * @return array{0: ExamAttempt}
     */
    private function createAttemptWithDurations(array $durations, ?User $user = null): array
    {
        $user ??= User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Ujian Waktu',
            'slug' => 'ujian-waktu-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::InProgress,
            'question_duration' => ['by_sort_order' => $durations],
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk-'.uniqid(),
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'materi-'.uniqid(),
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        foreach ($durations as $sortOrder => $seconds) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => 'Soal '.$sortOrder,
                'difficulty' => 'medium',
                'is_active' => true,
            ]);

            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => (int) $sortOrder,
            ]);
        }

        return [$attempt->fresh(['answers.question.subject', 'exam'])];
    }
}
