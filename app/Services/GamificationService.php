<?php

namespace App\Services;

use App\Enums\DevotionBadge;
use App\Models\AudioLearningSession;
use App\Models\ExamAttempt;
use App\Models\FlashcardReviewSession;
use App\Models\User;
use App\Models\XpReward;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    public const TESTIMONIAL_XP_REWARD = 200;

    public const EXAM_PASS_XP_REWARD = 100;

    public const EXAM_FAIL_XP_REWARD = 10;

    public const DUEL_WIN_XP_REWARD = 15;

    public const DUEL_LOSE_XP_REWARD = 1;

    public function totalXpExpression(): string
    {
        return 'COALESCE(audio_xp_totals.audio_xp, 0) + COALESCE(flashcard_xp_totals.flashcard_xp, 0) + COALESCE(reward_xp_totals.reward_xp, 0)';
    }

    public function joinTotalXp(Builder $query, string $userIdColumn = 'users.id'): Builder
    {
        $audioXpSubquery = DB::table('audio_learning_sessions')
            ->select('user_id', DB::raw('COALESCE(SUM(xp_earned), 0) as audio_xp'))
            ->groupBy('user_id');

        $flashcardXpSubquery = DB::table('flashcard_review_sessions')
            ->select('user_id', DB::raw('COALESCE(SUM(xp_earned), 0) as flashcard_xp'))
            ->groupBy('user_id');

        $rewardXpSubquery = DB::table('xp_rewards')
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as reward_xp'))
            ->groupBy('user_id');

        return $query
            ->leftJoinSub($audioXpSubquery, 'audio_xp_totals', 'audio_xp_totals.user_id', '=', $userIdColumn)
            ->leftJoinSub($flashcardXpSubquery, 'flashcard_xp_totals', 'flashcard_xp_totals.user_id', '=', $userIdColumn)
            ->leftJoinSub($rewardXpSubquery, 'reward_xp_totals', 'reward_xp_totals.user_id', '=', $userIdColumn);
    }

    /** @return array{value: string, label: string, description: string, classes: string} */
    public function devotionBadgeForXp(int $xp): array
    {
        return DevotionBadge::fromXp($xp)->toArray();
    }

    /**
     * @return array{
     *     xp: int,
     *     current_badge: array{value: string, label: string, description: string, classes: string},
     *     next_badge: ?array{value: string, label: string, description: string, classes: string},
     *     xp_to_next: int,
     *     progress_percent: int,
     *     is_max_tier: bool,
     *     tiers: list<array{value: string, label: string, description: string, classes: string, min_xp: int, is_current: bool, is_unlocked: bool}>
     * }
     */
    public function devotionProgress(int $xp): array
    {
        $current = DevotionBadge::fromXp($xp);
        $next = $current->next();

        $tiers = collect(DevotionBadge::ladder())
            ->map(fn (DevotionBadge $badge) => [
                ...$badge->toArray(),
                'min_xp' => $badge->minXp(),
                'is_current' => $badge === $current,
                'is_unlocked' => $xp >= $badge->minXp(),
            ])
            ->values()
            ->all();

        if ($next === null) {
            return [
                'xp' => $xp,
                'current_badge' => $current->toArray(),
                'next_badge' => null,
                'xp_to_next' => 0,
                'progress_percent' => 100,
                'is_max_tier' => true,
                'tiers' => $tiers,
            ];
        }

        $tierStart = $current->minXp();
        $nextThreshold = $next->minXp();
        $span = $nextThreshold - $tierStart;
        $progress = $span > 0
            ? min(100, max(0, (int) round((($xp - $tierStart) / $span) * 100)))
            : 0;

        return [
            'xp' => $xp,
            'current_badge' => $current->toArray(),
            'next_badge' => $next->toArray(),
            'xp_to_next' => max(0, $nextThreshold - $xp),
            'progress_percent' => $progress,
            'is_max_tier' => false,
            'tiers' => $tiers,
        ];
    }

    public function totalXp(User $user): int
    {
        $audioXp = (int) AudioLearningSession::query()
            ->where('user_id', $user->id)
            ->sum('xp_earned');

        $flashcardXp = (int) FlashcardReviewSession::query()
            ->where('user_id', $user->id)
            ->sum('xp_earned');

        $rewardXp = (int) XpReward::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        return $audioXp + $flashcardXp + $rewardXp;
    }

    public function awardXp(User $user, string $sourceType, int $sourceId, int $amount): ?XpReward
    {
        return XpReward::query()->firstOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'user_id' => $user->id,
                'amount' => $amount,
            ],
        );
    }

    public function awardExamAttemptXp(ExamAttempt $attempt, User $user): ?XpReward
    {
        if ($attempt->duel_session_id !== null) {
            return null;
        }

        $passed = exam_attempt_passes(
            $attempt->score_twk,
            $attempt->score_tiu,
            $attempt->score_tkp,
            $attempt->total_score,
        );

        $amount = $passed ? self::EXAM_PASS_XP_REWARD : self::EXAM_FAIL_XP_REWARD;

        return $this->awardXp($user, ExamAttempt::class, $attempt->id, $amount);
    }

    public function awardDuelAttemptXp(ExamAttempt $attempt, User $user, bool $won): ?XpReward
    {
        if ($attempt->duel_session_id === null) {
            return null;
        }

        $amount = $won ? self::DUEL_WIN_XP_REWARD : self::DUEL_LOSE_XP_REWARD;

        return $this->awardXp($user, ExamAttempt::class, $attempt->id, $amount);
    }
}
