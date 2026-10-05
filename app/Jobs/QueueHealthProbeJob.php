<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Tiny job used by `php artisan exam:preflight` to prove a queue worker is
 * really consuming the configured queue (e.g. it was restarted after the
 * switch to Redis). It only leaves a short-lived marker in the cache.
 */
class QueueHealthProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $token) {}

    public function handle(): void
    {
        Cache::put(self::cacheKey($this->token), true, now()->addMinutes(5));
    }

    public static function cacheKey(string $token): string
    {
        return 'preflight:queue-probe:'.$token;
    }
}
