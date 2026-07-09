<?php

namespace App\Services;

use App\Enums\DailyActivityType;
use App\Models\AudioLearningSession;
use App\Models\DailyActivityLog;
use App\Models\ExamAttempt;
use App\Models\FlashcardReviewSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyStreakService
{
    public const MULTIPLIER_NORMAL = 1.0;

    public const MULTIPLIER_BONUS = 1.2;

    public const MULTIPLIER_MAX = 1.5;

    public const BONUS_STREAK_START = 4;

    public const MAX_STREAK_START = 8;

    public function logActivity(User $user, DailyActivityType $type, ?int $sourceId = null): DailyActivityLog
    {
        return DailyActivityLog::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'activity_type' => $type,
                'source_id' => $sourceId ?? 0,
                'activity_date' => now()->toDateString(),
            ],
        );
    }

    public function hasCompletedCheatSheetToday(User $user, int $materialId): bool
    {
        return DailyActivityLog::query()
            ->where('user_id', $user->id)
            ->where('activity_type', DailyActivityType::CheatSheet)
            ->where('source_id', $materialId)
            ->whereDate('activity_date', now()->toDateString())
            ->exists();
    }

    public function dailyStreak(User $user): int
    {
        $dates = $this->qualifyingActivityDates($user);

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $firstDate = $dates->first();

        if (! $firstDate->equalTo($today) && ! $firstDate->equalTo($yesterday)) {
            return 0;
        }

        $streak = 1;
        $expected = $firstDate->copy()->subDay();

        foreach ($dates->skip(1) as $date) {
            if (! $date->equalTo($expected)) {
                break;
            }

            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    public function xpMultiplier(int $streak): float
    {
        if ($streak >= self::MAX_STREAK_START) {
            return self::MULTIPLIER_MAX;
        }

        if ($streak >= self::BONUS_STREAK_START) {
            return self::MULTIPLIER_BONUS;
        }

        return self::MULTIPLIER_NORMAL;
    }

    public function applyMultiplier(int $baseXp, int $streak): int
    {
        if ($baseXp <= 0) {
            return 0;
        }

        return (int) round($baseXp * $this->xpMultiplier($streak));
    }

    /**
     * @return array{
     *     streak: int,
     *     multiplier: float,
     *     multiplier_label: string,
     *     next_tier_at: ?int,
     *     next_multiplier_label: ?string
     * }
     */
    public function streakInfo(User $user): array
    {
        $streak = $this->dailyStreak($user);
        $multiplier = $this->xpMultiplier($streak);

        $nextTierAt = match (true) {
            $streak >= self::MAX_STREAK_START => null,
            $streak >= self::BONUS_STREAK_START => self::MAX_STREAK_START,
            default => self::BONUS_STREAK_START,
        };

        $nextMultiplierLabel = match (true) {
            $streak >= self::MAX_STREAK_START => null,
            $streak >= self::BONUS_STREAK_START => $this->formatMultiplierLabel(self::MULTIPLIER_MAX),
            default => $this->formatMultiplierLabel(self::MULTIPLIER_BONUS),
        };

        return [
            'streak' => $streak,
            'multiplier' => $multiplier,
            'multiplier_label' => $this->formatMultiplierLabel($multiplier),
            'next_tier_at' => $nextTierAt,
            'next_multiplier_label' => $nextMultiplierLabel,
        ];
    }

    public function formatMultiplierLabel(float $multiplier): string
    {
        return match ($multiplier) {
            self::MULTIPLIER_MAX => '1.5x',
            self::MULTIPLIER_BONUS => '1.2x',
            default => '1x',
        };
    }

    /** @return Collection<int, Carbon> */
    private function qualifyingActivityDates(User $user): Collection
    {
        $logDates = DailyActivityLog::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->orderByDesc('activity_date')
            ->pluck('activity_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        $audioDates = AudioLearningSession::query()
            ->where('user_id', $user->id)
            ->selectRaw('DATE(completed_at) as session_date')
            ->distinct()
            ->pluck('session_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        $flashcardDates = FlashcardReviewSession::query()
            ->where('user_id', $user->id)
            ->selectRaw('DATE(completed_at) as session_date')
            ->distinct()
            ->pluck('session_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        $duelDates = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereNotNull('duel_session_id')
            ->whereNotNull('submitted_at')
            ->selectRaw('DATE(submitted_at) as session_date')
            ->distinct()
            ->pluck('session_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        return $logDates
            ->merge($audioDates)
            ->merge($flashcardDates)
            ->merge($duelDates)
            ->unique(fn (Carbon $date) => $date->toDateString())
            ->sortByDesc(fn (Carbon $date) => $date->timestamp)
            ->values();
    }
}
