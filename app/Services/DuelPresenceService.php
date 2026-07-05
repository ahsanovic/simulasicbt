<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DuelPresenceService
{
    public const ONLINE_THRESHOLD_MINUTES = 3;

    public function touch(User $user): void
    {
        $cacheKey = "peserta-presence:{$user->id}";

        if (Cache::has($cacheKey)) {
            return;
        }

        $user->update(['last_seen_at' => now()]);
        Cache::put($cacheKey, true, now()->addMinute());
    }

    public function isOnline(User $user): bool
    {
        if ($user->last_seen_at === null) {
            return false;
        }

        return $user->last_seen_at->gte(now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES));
    }
}
