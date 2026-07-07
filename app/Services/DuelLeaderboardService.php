<?php

namespace App\Services;

use App\Enums\DuelSessionStatus;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuelLeaderboardService
{
    private const int DEFAULT_LIMIT = 10;

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * @return array{
     *     entries: Collection<int, array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, is_current: bool}>,
     *     current_user: ?array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, is_current: bool}
     * }
     */
    public function getLeaderboard(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        $rows = $this->rankedRowsQuery()->get();

        $entries = $rows->take($limit)->values()->map(fn ($row, int $index) => $this->mapRow($row, $index + 1, $userId));

        $currentUser = null;

        if (! $entries->contains(fn (array $entry) => $entry['is_current'])) {
            $userRow = $rows->firstWhere('id', $userId);

            if ($userRow) {
                $rank = $rows->search(fn ($item) => (int) $item->id === $userId);
                $currentUser = $this->mapRow($userRow, $rank + 1, $userId);
            }
        }

        return [
            'entries' => $entries,
            'current_user' => $currentUser,
        ];
    }

    public function getUserRank(int $userId): ?int
    {
        $rows = $this->rankedRowsQuery()->get();
        $rank = $rows->search(fn ($item) => (int) $item->id === $userId);

        return $rank === false ? null : $rank + 1;
    }

    private function rankedRowsQuery()
    {
        $completed = DuelSessionStatus::Completed->value;
        $peserta = UserRole::Peserta->value;
        $xpExpression = $this->gamificationService->totalXpExpression();

        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                DB::raw("(
                    SELECT COUNT(*) FROM duel_sessions ds
                    WHERE ds.status = '{$completed}'
                    AND (
                        ds.host_user_id = users.id
                        OR (ds.opponent_user_id = users.id AND ds.is_bot_opponent = 0)
                    )
                ) as duels"),
                DB::raw("(
                    SELECT COUNT(*) FROM duel_sessions ds
                    WHERE ds.status = '{$completed}' AND ds.winner_user_id = users.id
                ) as wins"),
                DB::raw("{$xpExpression} as total_xp"),
            )
            ->where('users.role', $peserta)
            ->having('duels', '>=', 1)
            ->orderByDesc('wins')
            ->orderByDesc('duels')
            ->orderBy('users.name');

        return $this->gamificationService->joinTotalXp($query);
    }

    /** @return array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, xp: int, devotion_badge: array{value: string, label: string, description: string, classes: string}, is_current: bool} */
    private function mapRow(object $row, int $rank, int $userId): array
    {
        $duels = (int) $row->duels;
        $wins = (int) $row->wins;
        $xp = (int) ($row->total_xp ?? 0);

        return [
            'rank' => $rank,
            'user_id' => (int) $row->id,
            'name' => $row->name,
            'wins' => $wins,
            'duels' => $duels,
            'win_rate' => $duels > 0 ? (int) round(($wins / $duels) * 100) : 0,
            'xp' => $xp,
            'devotion_badge' => $this->gamificationService->devotionBadgeForXp($xp),
            'is_current' => (int) $row->id === $userId,
        ];
    }
}
