<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\SystemHealth\Index;
use App\Models\SystemHealthLog;
use App\Models\User;
use App\Support\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SystemHealthPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No Redis in tests: the Redis checks fail fast instead of probing a server.
        config(['cache.default' => 'array', 'session.driver' => 'array', 'database.redis.default.port' => 1]);
    }

    public function test_only_admins_can_open_the_page_and_reports(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);
        $log = $this->makeLog();

        $this->actingAs($peserta)->get(route('admin.system-health.index'))->assertForbidden();
        $this->actingAs($peserta)->get(route('admin.system-health.report', $log))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSee('Kesehatan Sistem')
            ->assertSee('Jalankan Pemeriksaan');
    }

    public function test_running_the_checks_waits_for_the_worker_then_saves_a_report(): void
    {
        config(['queue.default' => 'sync']); // the probe job runs at once
        $admin = $this->admin();

        $page = Livewire::actingAs($admin)->test(Index::class)->call('runChecks');

        // The worker probe is settled by polling, not inside the click.
        $this->assertNotNull($page->get('probeToken'));
        $this->assertSame(0, SystemHealthLog::query()->count());

        $page->call('checkWorkerProbe')->assertSet('probeToken', null);

        $log = SystemHealthLog::query()->sole();
        $worker = collect($log->results)->firstWhere('label', SystemHealth::WORKER_LABEL);
        $this->assertSame(SystemHealth::PASS, $worker['status']);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(SystemHealth::FAIL, $log->status, 'CACHE_STORE/SESSION_DRIVER are not redis here.');
        $this->assertArrayHasKey('disk_free_gb', $log->metrics);
        $page->assertSet('lastLogId', $log->id)->assertSee('Cetak laporan');
    }

    public function test_a_worker_that_never_answers_fails_after_the_timeout(): void
    {
        Queue::fake();
        $page = Livewire::actingAs($this->admin())->test(Index::class)->call('runChecks');

        $page->call('checkWorkerProbe');
        $this->assertNotNull($page->get('probeToken'), 'Still waiting before the timeout.');

        $this->travel(SystemHealth::WORKER_TIMEOUT_SECONDS + 1)->seconds();
        $page->call('checkWorkerProbe')->assertSet('probeToken', null);

        $worker = collect(SystemHealthLog::query()->sole()->results)->firstWhere('label', SystemHealth::WORKER_LABEL);
        $this->assertSame(SystemHealth::FAIL, $worker['status']);
        $this->assertStringContainsString('docker restart simulasicbt-queue', $worker['hint']);
    }

    public function test_report_prints_the_saved_results(): void
    {
        $log = $this->makeLog();

        $this->actingAs($this->admin())->get(route('admin.system-health.report', $log))
            ->assertOk()
            ->assertSee('Laporan Kesehatan Sistem')
            ->assertSee('TIDAK SIAP')
            ->assertSee('CACHE_STORE = redis')
            ->assertSee('CACHE_STORE=array')
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_only_the_newest_runs_are_kept(): void
    {
        foreach (range(1, SystemHealthLog::KEEP + 3) as $i) {
            $this->makeLog();
        }

        SystemHealthLog::prune();

        $this->assertSame(SystemHealthLog::KEEP, SystemHealthLog::query()->count());
        $this->assertSame(4, SystemHealthLog::query()->min('id'));
    }

    public function test_recent_log_entries_show_only_warnings_and_errors_newest_first(): void
    {
        $storage = storage_path('framework/testing/health-'.uniqid());
        File::ensureDirectoryExists($storage.'/logs');
        File::put($storage.'/logs/laravel.log', implode("\n", [
            '[2026-10-06 07:00:00] production.INFO: login ok',
            '[2026-10-06 07:01:00] production.WARNING: Stale exam answer ignored {"answer_id":5}',
            '#0 /var/www/vendor/stack/trace.php(12)',
            '[2026-10-06 07:02:00] production.ERROR: SQLSTATE[HY000] gone away',
        ])."\n");
        $original = storage_path();
        $this->app->useStoragePath($storage);

        try {
            $entries = SystemHealth::recentLogEntries();
        } finally {
            $this->app->useStoragePath($original);
            File::deleteDirectory($storage);
        }

        $this->assertSame(['ERROR', 'WARNING'], array_column($entries, 'level'));
        $this->assertSame('2026-10-06 07:02:00', $entries[0]['time']);
        $this->assertStringStartsWith('SQLSTATE[HY000]', $entries[0]['message']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function makeLog(): SystemHealthLog
    {
        $results = [
            ['section' => 'Cache', 'label' => 'CACHE_STORE = redis', 'status' => SystemHealth::FAIL, 'hint' => 'CACHE_STORE=array'],
            ['section' => 'Database', 'label' => 'Koneksi database', 'status' => SystemHealth::PASS, 'hint' => ''],
        ];

        return SystemHealthLog::query()->create([...SystemHealth::summarize($results), 'results' => $results, 'metrics' => SystemHealth::metrics()]);
    }
}
