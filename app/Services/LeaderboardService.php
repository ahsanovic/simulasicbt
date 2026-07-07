<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    private const int DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * @return array{
     *     entries: Collection<int, array{rank: int, user_id: int, name: string, score: int, xp: int, devotion_badge: array{value: string, label: string, description: string, classes: string}, is_current: bool}>,
     *     current_user: ?array{rank: int, user_id: int, name: string, score: int, xp: int, devotion_badge: array{value: string, label: string, description: string, classes: string}, is_current: bool}
     * }
     */
    public function getLiveLeaderboard(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        $rows = $this->bestScoresQuery()->get();

        $entries = $rows->take($limit)->values()->map(fn ($row, int $index) => $this->mapRow($row, $index + 1, $userId));

        $currentUser = null;

        if (! $entries->contains(fn (array $entry) => $entry['is_current'])) {
            $currentUser = $this->getCurrentUserEntry($userId);
        }

        return [
            'entries' => $entries,
            'current_user' => $currentUser,
        ];
    }

    public function getUserRank(int $userId): ?int
    {
        $entry = $this->getCurrentUserEntry($userId);

        return $entry['rank'] ?? null;
    }

    /** @return ?array{rank: int, user_id: int, name: string, score: int, xp: int, devotion_badge: array{value: string, label: string, description: string, classes: string}, is_current: bool} */
    private function getCurrentUserEntry(int $userId): ?array
    {
        $bestScore = DB::table('exam_attempts')
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->max('total_score');

        if ($bestScore === null) {
            return null;
        }

        $bestScore = (int) $bestScore;

        $rank = DB::query()
            ->fromSub($this->bestScoresQuery(), 'ranked')
            ->where('best_score', '>', $bestScore)
            ->count() + 1;

        $userRow = $this->bestScoresQuery()
            ->where('users.id', $userId)
            ->first();

        if ($userRow === null) {
            return null;
        }

        return $this->mapRow($userRow, $rank, $userId);
    }

    private function bestScoresQuery()
    {
        $xpExpression = $this->gamificationService->totalXpExpression();

        $query = DB::table('exam_attempts')
            ->join('users', 'users.id', '=', 'exam_attempts.user_id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('MAX(exam_attempts.total_score) as best_score'),
                DB::raw("{$xpExpression} as total_xp"),
            )
            ->where('users.role', UserRole::Peserta->value)
            ->where('exam_attempts.status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('exam_attempts.total_score')
            ->groupBy('users.id', 'users.name', 'audio_xp_totals.audio_xp', 'reward_xp_totals.reward_xp')
            ->orderByDesc('best_score')
            ->orderBy('users.name');

        return $this->gamificationService->joinTotalXp($query);
    }

    /** @return array{rank: int, user_id: int, name: string, score: int, xp: int, devotion_badge: array{value: string, label: string, description: string, classes: string}, is_current: bool} */
    private function mapRow(object $row, int $rank, int $userId): array
    {
        $xp = (int) ($row->total_xp ?? 0);

        return [
            'rank' => $rank,
            'user_id' => (int) $row->id,
            'name' => $row->name,
            'score' => (int) $row->best_score,
            'xp' => $xp,
            'devotion_badge' => $this->gamificationService->devotionBadgeForXp($xp),
            'is_current' => (int) $row->id === $userId,
        ];
    }
}
