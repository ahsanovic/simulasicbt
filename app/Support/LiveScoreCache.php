<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared, short-lived snapshot of livescore boards.
 *
 * A board is built from aggregate queries over every answer of the event.
 * Anyone can open the public livescore, so building it once per viewer per
 * poll would grow with the audience. Instead every viewer of the same board
 * reads one snapshot that is rebuilt at most every TTL seconds.
 *
 * TTL 5 s + screens refreshing every 10 s: a new score shows within ~15 s
 * (about 7 s on average), while the board is built at most 12 times a minute
 * whatever the number of viewers.
 *
 * Each event has a version number in the key: changing attempts from the
 * admin board (add time, reset) bumps it, so every snapshot of that event —
 * admin and public, any session — is rebuilt on the next read.
 */
final class LiveScoreCache
{
    public const TTL_SECONDS = 5;

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function remember(int $eventId, string $board, Closure $build): mixed
    {
        return Cache::remember(
            'livescore:'.$eventId.':v'.self::version($eventId).':'.$board,
            self::TTL_SECONDS,
            $build,
        );
    }

    /**
     * An attempt of the event was just scored: the boards show the saved score
     * on their next poll instead of a snapshot taken while it was in progress
     * (which misses the answers sent together with "Selesai"). Runs after the
     * submit commits; a cache hiccup must not fail or undo the submit.
     */
    public static function bustAfterSubmit(?int $eventId): void
    {
        if ($eventId === null) {
            return;
        }

        DB::afterCommit(function () use ($eventId) {
            try {
                self::bust($eventId);
            } catch (Throwable $exception) {
                Log::warning('Livescore not refreshed after submit.', [
                    'event_id' => $eventId,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    /** Make every cached board of the event stale at once. */
    public static function bust(int $eventId): void
    {
        $key = self::versionKey($eventId);

        Cache::add($key, 1, now()->addDay());
        Cache::increment($key);
    }

    private static function version(int $eventId): int
    {
        return (int) Cache::get(self::versionKey($eventId), 1);
    }

    private static function versionKey(int $eventId): string
    {
        return 'livescore:'.$eventId.':version';
    }
}
