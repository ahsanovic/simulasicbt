<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAttemptXpRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_100_xp_when_attempt_passes_threshold(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        $reward = app(GamificationService::class)->awardExamAttemptXp($attempt, $user);

        $this->assertNotNull($reward);
        $this->assertSame(GamificationService::EXAM_PASS_XP_REWARD, $reward->amount);
        $this->assertDatabaseHas('xp_rewards', [
            'user_id' => $user->id,
            'source_type' => ExamAttempt::class,
            'source_id' => $attempt->id,
            'amount' => 100,
        ]);
    }

    public function test_awards_10_xp_when_attempt_fails_threshold(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 50,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 305,
        ]);

        $reward = app(GamificationService::class)->awardExamAttemptXp($attempt, $user);

        $this->assertNotNull($reward);
        $this->assertSame(GamificationService::EXAM_FAIL_XP_REWARD, $reward->amount);
    }

    public function test_does_not_award_xp_for_duel_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
            'duel_session_id' => 1,
        ]);

        $reward = app(GamificationService::class)->awardExamAttemptXp($attempt, $user);

        $this->assertNull($reward);
        $this->assertSame(0, XpReward::query()->count());
    }

    public function test_does_not_duplicate_xp_reward_for_same_attempt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        $service = app(GamificationService::class);
        $service->awardExamAttemptXp($attempt, $user);
        $service->awardExamAttemptXp($attempt, $user);

        $this->assertSame(1, XpReward::query()->where('user_id', $user->id)->count());
        $this->assertSame(100, $service->totalXp($user));
    }

    /** @param array<string, mixed> $overrides */
    private function createAttempt(User $user, array $overrides = []): ExamAttempt
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Test',
            'slug' => 'simulasi-test',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        return ExamAttempt::query()->create(array_merge([
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
        ], $overrides));
    }
}
