<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class XpLeaderboardService
{
    private const int DEFAULT_LIMIT = 10;

    /**
     * @return array{
     *     entries: Collection<int, array{rank: int, user_id: int, name: string, xp: int, is_current: bool}>,
     *     current_user: ?array{rank: int, user_id: int, name: string, xp: int, is_current: bool}
     * }
     */
    public function getLeaderboard(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        $rows = $this->totalXpQuery()->get();

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
        $rows = $this->totalXpQuery()->get();
        $rank = $rows->search(fn ($item) => (int) $item->id === $userId);

        return $rank === false ? null : $rank + 1;
    }

    /** @return array{rank: int, user_id: int, name: string, xp: int, is_current: bool}|null */
    public function getUserEntry(int $userId): ?array
    {
        $rows = $this->totalXpQuery()->get();
        $userRow = $rows->firstWhere('id', $userId);

        if ($userRow === null) {
            return null;
        }

        $rank = $rows->search(fn ($item) => (int) $item->id === $userId);

        return $this->mapRow($userRow, $rank + 1, $userId);
    }

    private function totalXpQuery()
    {
        $audioXpSubquery = DB::table('audio_learning_sessions')
            ->select('user_id', DB::raw('COALESCE(SUM(xp_earned), 0) as audio_xp'))
            ->groupBy('user_id');

        $rewardXpSubquery = DB::table('xp_rewards')
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as reward_xp'))
            ->groupBy('user_id');

        return DB::table('users')
            ->leftJoinSub($audioXpSubquery, 'audio_xp_totals', 'audio_xp_totals.user_id', '=', 'users.id')
            ->leftJoinSub($rewardXpSubquery, 'reward_xp_totals', 'reward_xp_totals.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COALESCE(audio_xp_totals.audio_xp, 0) + COALESCE(reward_xp_totals.reward_xp, 0) as total_xp'),
            )
            ->where('users.role', UserRole::Peserta->value)
            ->having('total_xp', '>', 0)
            ->orderByDesc('total_xp')
            ->orderBy('users.name');
    }

    /** @return array{rank: int, user_id: int, name: string, xp: int, is_current: bool} */
    private function mapRow(object $row, int $rank, int $userId): array
    {
        return [
            'rank' => $rank,
            'user_id' => (int) $row->id,
            'name' => $row->name,
            'xp' => (int) $row->total_xp,
            'is_current' => (int) $row->id === $userId,
        ];
    }
}
