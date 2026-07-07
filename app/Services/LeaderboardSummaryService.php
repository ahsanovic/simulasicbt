<?php

namespace App\Services;

class LeaderboardSummaryService
{
    public function __construct(
        private readonly LeaderboardService $scoreLeaderboard,
        private readonly DuelLeaderboardService $duelLeaderboard,
        private readonly XpLeaderboardService $xpLeaderboard,
    ) {}

    /**
     * @return array{
     *     score: ?int,
     *     duel: ?int,
     *     xp: ?int
     * }
     */
    public function getRanks(int $userId): array
    {
        return [
            'score' => $this->scoreLeaderboard->getUserRank($userId),
            'duel' => $this->duelLeaderboard->getUserRank($userId),
            'xp' => $this->xpLeaderboard->getUserRank($userId),
        ];
    }
}
