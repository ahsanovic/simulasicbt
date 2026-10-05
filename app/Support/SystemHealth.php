<?php

namespace App\Support;

use App\Enums\ExamAttemptStatus;
use App\Jobs\ExportExamResultsJob;
use App\Jobs\QueueHealthProbeJob;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

/**
 * Readiness checks before a large exam, shared by `php artisan exam:preflight`
 * and the admin "Kesehatan Sistem" page. Read-only: every check runs against
 * the live configuration and reports LULUS / PERINGATAN / GAGAL.
 *
 * Every item is ['section', 'label', 'status', 'hint'] with status pass, warn,
 * fail, info (a measurement, not a check) or pending (the worker probe while
 * the page waits for the queue worker).
 */
final class SystemHealth
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const INFO = 'info';

    public const PENDING = 'pending';

    public const WORKER_LABEL = 'Worker queue memproses job (uji kirim-terima, maks 20 detik)';

    public const WORKER_TIMEOUT_SECONDS = 20;

    /** @var list<array{section: string, label: string, status: string, hint: string}> */
    private array $items = [];

    private string $section = '';

    /**
     * Run every check. The queue worker probe is delegated to $workerProbe:
     * it returns true/false (or throws) when it waits for the worker itself,
     * or null to leave the item pending for the caller to settle later.
     *
     * @param  Closure(): ?bool  $workerProbe
     * @return list<array{section: string, label: string, status: string, hint: string}>
     */
    public function run(Closure $workerProbe): array
    {
        $this->items = [];

        $this->section('Aplikasi');
        $this->check('APP_ENV = production', app()->environment('production'), 'APP_ENV='.app()->environment(), warnOnly: true);
        $this->check('APP_DEBUG = false', ! config('app.debug'), 'APP_DEBUG masih true: error detail tampil ke peserta');
        $this->check('Konfigurasi di-cache (php artisan optimize)', app()->configurationIsCached(), 'Jalankan: php artisan optimize', warnOnly: true);
        $this->check('Route di-cache', app()->routesAreCached(), 'Jalankan: php artisan optimize', warnOnly: true);

        $this->section('Redis');
        $redisOk = $this->attempt('Koneksi Redis (PING)', function () {
            $pong = Redis::connection()->command('ping');

            return in_array(strtolower((string) $pong), ['pong', '1', 'true'], true) || $pong === true;
        });

        if ($redisOk) {
            try {
                $info = self::redisInfo();
                $policy = self::redisConfig('maxmemory-policy');
                $maxMemory = (int) self::redisConfig('maxmemory');
                $used = (int) ($info['used_memory'] ?? 0);

                $this->info('versi '.($info['redis_version'] ?? '?').', klien '.config('database.redis.client').', memori terpakai '.self::mb($used).' / batas '.($maxMemory ? self::mb($maxMemory) : 'tanpa batas'));
                $this->check('maxmemory-policy = noeviction (session tidak pernah dibuang)', $policy === 'noeviction', "Sekarang: {$policy}. Session bisa terhapus saat memori penuh -> peserta ter-logout");
                $this->check('Sisa memori Redis cukup (< 70% terpakai)', $maxMemory === 0 || $used < $maxMemory * 0.7, 'Memori Redis hampir penuh: naikkan --maxmemory');
                $this->check('Persistensi AOF aktif (session selamat saat Redis restart)', ($info['aof_enabled'] ?? '0') === '1', 'Tanpa AOF, restart Redis me-logout semua peserta. Jalankan Redis dengan --appendonly yes');
            } catch (Throwable $e) {
                // e.g. INFO/CONFIG disabled on this Redis: report, don't crash.
                $this->check('Pengaturan memori & persistensi Redis terbaca', false, 'Tidak bisa membaca INFO/CONFIG Redis: '.Str::limit($e->getMessage(), 120), warnOnly: true);
            }
        }

        $this->section('Cache');
        $this->check('CACHE_STORE = redis', config('cache.default') === 'redis', 'CACHE_STORE='.config('cache.default'));
        $this->attempt('Tulis-baca cache', function () {
            $key = 'preflight:'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        });
        $this->attempt('Cache lock', function () {
            $lock = Cache::lock('preflight-lock', 5);
            $ok = $lock->get();
            $lock->release();

            return $ok;
        });
        $this->check(
            'Cache dan session di database Redis yang berbeda (cache:clear tidak me-logout peserta)',
            config('session.driver') !== 'redis' || self::sessionRedisDb() !== (string) config('database.redis.cache.database'),
            'Session dan cache satu database Redis: php artisan cache:clear akan menghapus semua session',
        );

        $this->section('Session');
        $this->check('SESSION_DRIVER = redis', config('session.driver') === 'redis', 'SESSION_DRIVER='.config('session.driver'));
        $this->attempt('Tulis-baca session di penyimpanan session', function () {
            $handler = app('session')->driver()->getHandler();
            $id = Str::random(40);
            $handler->write($id, 'preflight-ok');
            $ok = $handler->read($id) === 'preflight-ok';
            $handler->destroy($id);

            return $ok;
        });
        $this->check('SESSION_LIFETIME >= 120 menit', (int) config('session.lifetime') >= 120, 'SESSION_LIFETIME='.config('session.lifetime').' menit: peserta bisa ter-logout saat diam lama', warnOnly: true);

        $this->section('Queue');
        $this->check('QUEUE_CONNECTION = redis', config('queue.default') === 'redis', 'QUEUE_CONNECTION='.config('queue.default'));
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after');
        $exportTimeout = (int) ((new ReflectionClass(ExportExamResultsJob::class))->getDefaultProperties()['timeout'] ?? 0);
        $this->check("retry_after ({$retryAfter}s) > timeout export ({$exportTimeout}s)", $retryAfter > $exportTimeout, 'Export panjang bisa dijalankan dua kali. Set REDIS_QUEUE_RETRY_AFTER=1900');
        $this->attempt('Antrean queue terbaca', function () {
            $this->info('job menunggu: '.Queue::size());

            return true;
        });
        $this->workerItem($workerProbe);
        try {
            if (Schema::hasTable('jobs') && config('queue.default') !== 'database') {
                $left = DB::table('jobs')->count();
                $this->check('Tidak ada job tertinggal di antrean database lama', $left === 0, "{$left} job masih di tabel jobs (tidak akan diproses worker Redis)", warnOnly: true);
            }
        } catch (Throwable $e) {
            // Database problems are reported in the Database section below.
        }

        $this->section('Database');
        $dbOk = $this->attempt('Koneksi database', fn () => DB::select('select 1 as ok')[0]->ok == 1);

        if ($dbOk) {
            $this->check('Migrasi answer_version sudah dijalankan', Schema::hasColumn('exam_answers', 'answer_version') && Schema::hasColumn('skb_exam_answers', 'answer_version'), 'Jalankan: php artisan migrate --force');

            if (DB::getDriverName() === 'mysql') {
                $maxConnections = (int) (DB::selectOne("show variables like 'max_connections'")->Value ?? 0);
                $this->check("MySQL max_connections ({$maxConnections}) >= 200", $maxConnections >= 200, 'Naikkan max_connections di MySQL (minimal pm.max_children + 50)', warnOnly: true);
            }
        }

        return $this->items;
    }

    /**
     * Queue a tiny job for the worker. It proves a worker really consumes the
     * configured queue (e.g. it was restarted after switching to Redis).
     */
    public static function dispatchWorkerProbe(): string
    {
        $token = Str::random(16);
        QueueHealthProbeJob::dispatch($token);

        return $token;
    }

    public static function workerProbeDone(string $token): bool
    {
        if (! Cache::get(QueueHealthProbeJob::cacheKey($token))) {
            return false;
        }

        Cache::forget(QueueHealthProbeJob::cacheKey($token));

        return true;
    }

    public static function workerFailureHint(): string
    {
        return 'Job uji tidak diproses dalam '.self::WORKER_TIMEOUT_SECONDS.' detik. Restart container queue (docker restart simulasicbt-queue) dan pastikan worker memakai koneksi '.config('queue.default').'. Bisa juga worker sedang mengerjakan export panjang: ulangi setelahnya.';
    }

    /**
     * @param  list<array{section: string, label: string, status: string, hint: string}>  $items
     * @return array{status: string, passed: int, warnings: int, failures: int}
     */
    public static function summarize(array $items): array
    {
        $count = fn (string $status) => count(array_filter($items, fn (array $item) => $item['status'] === $status));
        $failures = $count(self::FAIL);
        $warnings = $count(self::WARN);

        return [
            'status' => $failures > 0 ? self::FAIL : ($warnings > 0 ? self::WARN : self::PASS),
            'passed' => $count(self::PASS),
            'warnings' => $warnings,
            'failures' => $failures,
        ];
    }

    /**
     * Light live figures for the admin page (polled): no aggregation, each one
     * independent so a failing source only blanks its own card.
     *
     * @return array<string, int|float|string|null>
     */
    public static function metrics(): array
    {
        $metrics = [];
        $safe = function (string $key, Closure $read) use (&$metrics): void {
            try {
                $metrics[$key] = $read();
            } catch (Throwable) {
                $metrics[$key] = null;
            }
        };

        $safe('skd_in_progress', fn () => DB::table('exam_attempts')->where('status', ExamAttemptStatus::InProgress->value)->where('expires_at', '>', now())->count());
        $safe('skb_in_progress', fn () => DB::table('skb_exam_attempts')->where('status', ExamAttemptStatus::InProgress->value)->where('expires_at', '>', now())->count());
        $safe('failed_jobs', fn () => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0);

        $usesRedis = in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);
        $redisInfo = null;
        $safe('redis_used_mb', function () use ($usesRedis, &$redisInfo) {
            if (! $usesRedis) {
                return null;
            }

            $redisInfo = self::redisInfo();

            return round(((int) ($redisInfo['used_memory'] ?? 0)) / 1048576, 1);
        });
        $safe('redis_max_mb', fn () => $redisInfo === null ? null : round(((int) self::redisConfig('maxmemory')) / 1048576, 1));
        $safe('redis_clients', fn () => $redisInfo === null ? null : (int) ($redisInfo['connected_clients'] ?? 0));

        $safe('db_connections', fn () => DB::getDriverName() === 'mysql' ? (int) (DB::selectOne("show global status like 'Threads_connected'")->Value ?? 0) : null);
        $safe('db_max_connections', fn () => DB::getDriverName() === 'mysql' ? (int) (DB::selectOne("show variables like 'max_connections'")->Value ?? 0) : null);

        $safe('opcache_memory_percent', function () {
            $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;

            if (! is_array($status) || ! isset($status['memory_usage'])) {
                return null;
            }

            $memory = $status['memory_usage'];
            $total = $memory['used_memory'] + $memory['free_memory'] + $memory['wasted_memory'];

            return $total > 0 ? round($memory['used_memory'] / $total * 100, 1) : null;
        });
        $safe('opcache_hit_rate', function () {
            $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;

            return is_array($status) && isset($status['opcache_statistics']['opcache_hit_rate'])
                ? round($status['opcache_statistics']['opcache_hit_rate'], 1)
                : null;
        });

        $safe('disk_free_gb', function () {
            $free = disk_free_space(storage_path());

            return $free === false ? null : round($free / 1073741824, 1);
        });

        return $metrics;
    }

    /**
     * The last warning/error entries of the application log: level, time and
     * the first line of the message only (no stack traces).
     *
     * @return list<array{time: string, level: string, message: string}>
     */
    public static function recentLogEntries(int $limit = 20): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];

        if ($files === []) {
            return [];
        }

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        try {
            $handle = fopen($files[0], 'rb');
            $size = filesize($files[0]);
            $length = min($size, 256 * 1024);
            fseek($handle, $size - $length);
            $tail = (string) fread($handle, $length);
            fclose($handle);
        } catch (Throwable) {
            return [];
        }

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+\-]+)\] \w+\.(WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $tail, $matches, PREG_SET_ORDER);

        $entries = array_map(fn (array $match) => [
            'time' => substr($match[1], 0, 19),
            'level' => $match[2],
            'message' => Str::limit(trim($match[3]), 220),
        ], $matches);

        return array_slice(array_reverse($entries), 0, $limit);
    }

    private function workerItem(Closure $workerProbe): void
    {
        try {
            $ok = $workerProbe();
        } catch (Throwable $e) {
            $this->check(self::WORKER_LABEL, false, Str::limit($e->getMessage(), 260));

            return;
        }

        if ($ok === null) {
            $this->items[] = ['section' => $this->section, 'label' => self::WORKER_LABEL, 'status' => self::PENDING, 'hint' => 'Menunggu worker queue...'];

            return;
        }

        $this->check(self::WORKER_LABEL, $ok, self::workerFailureHint());
    }

    private function section(string $title): void
    {
        $this->section = $title;
    }

    private function info(string $text): void
    {
        $this->items[] = ['section' => $this->section, 'label' => $text, 'status' => self::INFO, 'hint' => ''];
    }

    private function check(string $label, bool $ok, string $hint = '', bool $warnOnly = false): bool
    {
        $status = $ok ? self::PASS : ($warnOnly ? self::WARN : self::FAIL);
        $this->items[] = ['section' => $this->section, 'label' => $label, 'status' => $status, 'hint' => $ok ? '' : $hint];

        return $ok;
    }

    private function attempt(string $label, callable $probe): bool
    {
        try {
            return $this->check($label, (bool) $probe());
        } catch (Throwable $e) {
            return $this->check($label, false, Str::limit($e->getMessage(), 160));
        }
    }

    /** @return array<string, string> */
    private static function redisInfo(): array
    {
        $raw = Redis::connection()->command('info');

        if (is_array($raw)) {
            // predis groups INFO by section (and keyspace by db): keep the scalar fields.
            $info = [];
            array_walk_recursive($raw, function ($value, $key) use (&$info) {
                $info[(string) $key] = (string) $value;
            });

            return $info;
        }

        $info = [];
        foreach (preg_split('/\r?\n/', (string) $raw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $info[$k] = trim($v);
            }
        }

        return $info;
    }

    private static function redisConfig(string $key): string
    {
        $value = Redis::connection()->command('config', ['get', $key]);

        return (string) (is_array($value) ? (array_values($value)[1] ?? $value[$key] ?? '') : $value);
    }

    private static function sessionRedisDb(): string
    {
        $connection = config('session.connection') ?: 'default';

        return (string) config("database.redis.{$connection}.database");
    }

    private static function mb(int $bytes): string
    {
        return round($bytes / 1048576, 1).' MB';
    }
}
