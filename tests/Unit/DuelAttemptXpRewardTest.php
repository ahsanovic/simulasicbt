<?php

namespace Tests\Unit;

use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\DuelSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuelAttemptXpRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_15_xp_when_player_wins_duel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createDuelAttempt($user);

        $reward = app(GamificationService::class)->awardDuelAttemptXp($attempt, $user, true);

        $this->assertNotNull($reward);
        $this->assertSame(GamificationService::DUEL_WIN_XP_REWARD, $reward->amount);
    }

    public function test_awards_1_xp_when_player_loses_duel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createDuelAttempt($user);

        $reward = app(GamificationService::class)->awardDuelAttemptXp($attempt, $user, false);

        $this->assertNotNull($reward);
        $this->assertSame(GamificationService::DUEL_LOSE_XP_REWARD, $reward->amount);
    }

    public function test_does_not_award_xp_for_simulation_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createDuelAttempt($user, ['duel_session_id' => null]);

        $reward = app(GamificationService::class)->awardDuelAttemptXp($attempt, $user, true);

        $this->assertNull($reward);
        $this->assertSame(0, XpReward::query()->count());
    }

    private function createDuelAttempt(User $user, array $overrides = []): ExamAttempt
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Duel Mini-Tryout',
            'slug' => 'duel-mini-tryout',
            'duration_minutes' => 10,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all', 'is_duel' => true],
            'created_by' => $admin->id,
        ]);

        $session = DuelSession::query()->create([
            'code' => 'ABC123',
            'host_user_id' => $user->id,
            'question_ids' => [1, 2, 3],
            'status' => DuelSessionStatus::Completed,
            'match_type' => DuelMatchType::Random,
            'duration_minutes' => 10,
        ]);

        return ExamAttempt::query()->create(array_merge([
            'exam_id' => $exam->id,
            'duel_session_id' => $session->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 10,
            'score_tiu' => 10,
            'score_tkp' => 10,
            'total_score' => 30,
        ], $overrides));
    }
}
