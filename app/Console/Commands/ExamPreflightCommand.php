<?php

namespace App\Console\Commands;

use App\Jobs\ExportExamResultsJob;
use App\Jobs\QueueHealthProbeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Read-only readiness check before a large exam: runs every check against
 * the live configuration and prints LULUS / PERINGATAN / GAGAL, so the server
 * can be verified without a trial run. Exits non-zero when anything fails.
 */
class ExamPreflightCommand extends Command
{
    protected $signature = 'exam:preflight';

    protected $description = 'Cek kesiapan server sebelum ujian skala besar (Redis, session, cache, queue, database)';

    private int $failures = 0;

    public function handle(): int
    {
        $this->info('Pemeriksaan kesiapan ujian — '.now()->toDateTimeString());
        $this->newLine();

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
                $info = $this->redisInfo();
                $policy = $this->redisConfig('maxmemory-policy');
                $maxMemory = (int) $this->redisConfig('maxmemory');
                $used = (int) ($info['used_memory'] ?? 0);

                $this->line('    versi '.($info['redis_version'] ?? '?').', klien '.config('database.redis.client').', memori terpakai '.$this->mb($used).' / batas '.($maxMemory ? $this->mb($maxMemory) : 'tanpa batas'));
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
            config('session.driver') !== 'redis' || $this->sessionRedisDb() !== (string) config('database.redis.cache.database'),
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
            $this->line('    job menunggu: '.Queue::size());

            return true;
        });
        $this->checkWorkerConsumesQueue();
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

        $this->newLine();

        if ($this->failures > 0) {
            $this->error("{$this->failures} pemeriksaan GAGAL. Perbaiki sebelum ujian.");

            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan wajib LULUS.');

        return self::SUCCESS;
    }

    /**
     * Send a tiny job and wait for a worker to run it. Catches a worker that
     * was not restarted after switching QUEUE_CONNECTION (it keeps reading the
     * old queue, so exports would never run) or that cannot reach Redis.
     */
    private function checkWorkerConsumesQueue(): void
    {
        $this->attempt('Worker queue memproses job (uji kirim-terima, maks 20 detik)', function () {
            $token = Str::random(16);
            QueueHealthProbeJob::dispatch($token);

            $deadline = microtime(true) + 20;

            while (microtime(true) < $deadline) {
                if (Cache::get(QueueHealthProbeJob::cacheKey($token))) {
                    Cache::forget(QueueHealthProbeJob::cacheKey($token));

                    return true;
                }

                usleep(500_000);
            }

            throw new RuntimeException('Job uji tidak diproses dalam 20 detik. Restart container queue (docker restart simulasicbt-queue) dan pastikan worker memakai koneksi '.config('queue.default').'. Bisa juga worker sedang mengerjakan export panjang: ulangi setelahnya.');
        });
    }

    private function section(string $title): void
    {
        $this->line("<comment>{$title}</comment>");
    }

    private function check(string $label, bool $ok, string $hint = '', bool $warnOnly = false): bool
    {
        if ($ok) {
            $this->line("  <info>LULUS</info>      {$label}");
        } elseif ($warnOnly) {
            $this->line("  <comment>PERINGATAN</comment> {$label}".($hint ? " — {$hint}" : ''));
        } else {
            $this->failures++;
            $this->line("  <error>GAGAL</error>      {$label}".($hint ? " — {$hint}" : ''));
        }

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
    private function redisInfo(): array
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

    private function redisConfig(string $key): string
    {
        $value = Redis::connection()->command('config', ['get', $key]);

        return (string) (is_array($value) ? (array_values($value)[1] ?? $value[$key] ?? '') : $value);
    }

    private function sessionRedisDb(): string
    {
        $connection = config('session.connection') ?: 'default';

        return (string) config("database.redis.{$connection}.database");
    }

    private function mb(int $bytes): string
    {
        return round($bytes / 1048576, 1).' MB';
    }
}
