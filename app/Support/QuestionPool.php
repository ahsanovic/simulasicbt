<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The IDs of the active questions an exam may draw from (per SKD subject and
 * difficulty, per SKB jabatan), shared by every participant.
 *
 * Starting an exam used to pick questions with ORDER BY RAND(): MySQL read and
 * sorted the whole bank once per subject per participant, so hundreds of
 * participants pressing "Mulai" together meant hundreds of full sorts of the
 * same rows. Now the bank is read once into the cache and each participant's
 * random pick happens in PHP.
 *
 * The cache only speeds things up: callers re-check the picked IDs against the
 * database and fall back to the old query when the pool is stale, the feature
 * is switched off (EXAM_QUESTION_POOL=false) or the cache is unreachable.
 * Editing a question in the admin drops the pools (model events); bulk changes
 * that skip model events are bounded by the short fresh time.
 */
final class QuestionPool
{
    /** Seconds a pool is fresh, then seconds it may still be served while it is refreshed. */
    public const FRESH_SECONDS = 120;

    public const STALE_SECONDS = 600;

    /**
     * The cached IDs for $scope, or null when the pool cannot be used and the
     * caller must query the database itself.
     *
     * @param  'skd'|'skb'  $type
     * @param  Closure(): array<int, int>  $load
     * @return list<int>|null
     */
    public static function ids(string $type, string $scope, Closure $load): ?array
    {
        if (! config('exam.question_pool')) {
            return null;
        }

        try {
            $key = "question-pool:{$type}:v".self::version($type).":{$scope}";

            return array_map('intval', Cache::flexible($key, [self::FRESH_SECONDS, self::STALE_SECONDS], fn () => array_values($load())));
        } catch (Throwable $e) {
            Log::warning('Question pool unavailable, using the database', ['type' => $type, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * $count distinct IDs from $ids in random order (all of them, shuffled,
     * when there are not more than that).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function sample(array $ids, int $count): array
    {
        if ($count >= count($ids)) {
            shuffle($ids);

            return $ids;
        }

        $picked = array_map(fn ($key) => $ids[$key], (array) array_rand($ids, $count));
        shuffle($picked);

        return $picked;
    }

    /** @param  'skd'|'skb'  $type */
    public static function bust(string $type): void
    {
        try {
            $key = "question-pool:{$type}:version";
            Cache::add($key, 1, now()->addDays(30));
            Cache::increment($key);
        } catch (Throwable) {
            // The cache is down: nothing is being served from it either.
        }
    }

    private static function version(string $type): int
    {
        return (int) Cache::get("question-pool:{$type}:version", 1);
    }
}
