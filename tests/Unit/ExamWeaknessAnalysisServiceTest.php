<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\SkdTarget;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\ExamWeaknessAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamWeaknessAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weakness_stats_respect_skd_target_filter(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $cpnsExam = Exam::query()->create([
            'title' => 'CPNS',
            'slug' => 'cpns-weakness-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all', 'skd_target' => 'cpns'],
            'created_by' => $admin->id,
        ]);

        $kedinasanExam = Exam::query()->create([
            'title' => 'Kedinasan',
            'slug' => 'kedinasan-weakness-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all', 'skd_target' => 'sekolah_kedinasan'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $cpnsExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::Cpns,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $kedinasanExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::SekolahKedinasan,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 160,
            'total_score' => 315,
        ]);

        $service = app(ExamWeaknessAnalysisService::class);

        $this->assertSame(2, $service->getStatsForUser($user->id)['total_simulations']);
        $this->assertSame(1, $service->getStatsForUser($user->id, SkdTarget::Cpns)['total_simulations']);
        $this->assertSame(1, $service->getStatsForUser($user->id, SkdTarget::SekolahKedinasan)['total_simulations']);
    }
}
