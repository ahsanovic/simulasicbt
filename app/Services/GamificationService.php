<?php

namespace App\Services;

use App\Models\AudioLearningSession;
use App\Models\User;
use App\Models\XpReward;

class GamificationService
{
    public const TESTIMONIAL_XP_REWARD = 200;

    public function totalXp(User $user): int
    {
        $audioXp = (int) AudioLearningSession::query()
            ->where('user_id', $user->id)
            ->sum('xp_earned');

        $rewardXp = (int) XpReward::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        return $audioXp + $rewardXp;
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
}
