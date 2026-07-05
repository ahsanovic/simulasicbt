<?php

namespace App\Services;

use App\Enums\DuelSessionStatus;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuelLeaderboardService
{
    private const int LIMIT = 10;

    /**
     * @return array{
     *     entries: Collection<int, array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, is_current: bool}>,
     *     current_user: ?array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, is_current: bool}
     * }
     */
    public function getLeaderboard(int $userId): array
    {
        $completed = DuelSessionStatus::Completed->value;
        $peserta = UserRole::Peserta->value;

        $rows = DB::table('users')
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
            )
            ->where('users.role', $peserta)
            ->having('duels', '>=', 1)
            ->orderByDesc('wins')
            ->orderByDesc('duels')
            ->orderBy('users.name')
            ->get();

        $entries = $rows->take(self::LIMIT)->values()->map(fn ($row, int $index) => $this->mapRow($row, $index + 1, $userId));

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

    /** @return array{rank: int, user_id: int, name: string, wins: int, duels: int, win_rate: int, is_current: bool} */
    private function mapRow(object $row, int $rank, int $userId): array
    {
        $duels = (int) $row->duels;
        $wins = (int) $row->wins;

        return [
            'rank' => $rank,
            'user_id' => (int) $row->id,
            'name' => $row->name,
            'wins' => $wins,
            'duels' => $duels,
            'win_rate' => $duels > 0 ? (int) round(($wins / $duels) * 100) : 0,
            'is_current' => (int) $row->id === $userId,
        ];
    }
}
