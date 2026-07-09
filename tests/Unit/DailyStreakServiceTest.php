<?php

namespace Tests\Unit;

use App\Enums\DailyActivityType;
use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\DailyActivityLog;
use App\Models\DuelSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\AudioLearningService;
use App\Services\DailyStreakService;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyStreakServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_streak_is_zero_without_qualifying_activity(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->assertSame(0, app(DailyStreakService::class)->dailyStreak($user));
    }

    public function test_streak_counts_consecutive_days_from_activity_logs(): void
    {
        Carbon::setTestNow('2026-07-09 10:00:00');

        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(DailyStreakService::class);

        foreach (['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09'] as $date) {
            DailyActivityLog::query()->create([
                'user_id' => $user->id,
                'activity_type' => DailyActivityType::Audio,
                'source_id' => 0,
                'activity_date' => $date,
            ]);
        }

        $this->assertSame(4, $service->dailyStreak($user));
        $this->assertSame(DailyStreakService::MULTIPLIER_BONUS, $service->xpMultiplier(4));
    }

    public function test_streak_resets_after_missing_day(): void
    {
        Carbon::setTestNow('2026-07-09 10:00:00');

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        DailyActivityLog::query()->create([
            'user_id' => $user->id,
            'activity_type' => DailyActivityType::Audio,
            'source_id' => 0,
            'activity_date' => '2026-07-07',
        ]);

        $this->assertSame(0, app(DailyStreakService::class)->dailyStreak($user));
    }

    public function test_multiplier_tiers(): void
    {
        $service = app(DailyStreakService::class);

        $this->assertSame(1.0, $service->xpMultiplier(1));
        $this->assertSame(1.0, $service->xpMultiplier(3));
        $this->assertSame(1.2, $service->xpMultiplier(4));
        $this->assertSame(1.2, $service->xpMultiplier(7));
        $this->assertSame(1.5, $service->xpMultiplier(8));
        $this->assertSame(1.5, $service->xpMultiplier(30));
    }

    public function test_apply_multiplier_rounds_to_nearest_integer(): void
    {
        $service = app(DailyStreakService::class);

        $this->assertSame(18, $service->applyMultiplier(15, 4));
        $this->assertSame(1, $service->applyMultiplier(1, 4));
        $this->assertSame(15, $service->applyMultiplier(10, 8));
    }

    public function test_audio_session_applies_streak_multiplier_to_xp(): void
    {
        Carbon::setTestNow('2026-07-09 10:00:00');

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        foreach (['2026-07-06', '2026-07-07', '2026-07-08'] as $date) {
            DailyActivityLog::query()->create([
                'user_id' => $user->id,
                'activity_type' => DailyActivityType::Flashcard,
                'source_id' => 0,
                'activity_date' => $date,
            ]);
        }

        $session = app(AudioLearningService::class)->recordSession($user, SubjectCode::Twk, 10, 120);

        $this->assertSame(12, $session->xp_earned);
    }

    public function test_duel_xp_uses_streak_multiplier_on_day_eight(): void
    {
        Carbon::setTestNow('2026-07-09 10:00:00');

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        foreach (range(2, 8) as $day) {
            DailyActivityLog::query()->create([
                'user_id' => $user->id,
                'activity_type' => DailyActivityType::Audio,
                'source_id' => 0,
                'activity_date' => sprintf('2026-07-%02d', $day),
            ]);
        }

        $attempt = $this->createDuelAttempt($user);
        $reward = app(GamificationService::class)->awardDuelAttemptXp($attempt, $user, true);

        $this->assertNotNull($reward);
        $this->assertSame(23, $reward->amount);
    }

    public function test_log_activity_is_idempotent_per_day(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(DailyStreakService::class);

        $service->logActivity($user, DailyActivityType::Audio);
        $service->logActivity($user, DailyActivityType::Audio);

        $this->assertSame(1, DailyActivityLog::query()->where('user_id', $user->id)->count());
    }

    public function test_cheat_sheet_completion_is_tracked_per_material(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(DailyStreakService::class);

        $service->logActivity($user, DailyActivityType::CheatSheet, 42);

        $this->assertTrue($service->hasCompletedCheatSheetToday($user, 42));
        $this->assertFalse($service->hasCompletedCheatSheetToday($user, 99));
    }

    private function createDuelAttempt(User $user): ExamAttempt
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

        return ExamAttempt::query()->create([
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
        ]);
    }
}
