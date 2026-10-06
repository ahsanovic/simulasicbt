<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Jobs\GenerateExamPsychologyReportJob;
use App\Models\CoinTransaction;
use App\Models\XpReward;
use App\Services\ExamService;
use App\Services\ExamWeaknessAnalysisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Work that does not decide the score (psychology report, AI weakness stats)
 * runs only after the submit commits and never fails or undoes it; Mode Ujian,
 * scored on its own result page, skips that work and the XP/coin rewards.
 */
class ExamSubmitReportQueueTest extends ExamDeadlineEnforcementTest
{
    public function test_report_job_is_queued_only_after_the_submit_commits(): void
    {
        Queue::fake();
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        DB::transaction(function () use ($ctx) {
            app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);

            // A Redis worker would run it right away and still see the
            // attempt in progress.
            Queue::assertNothingPushed();
        });

        Queue::assertPushed(GenerateExamPsychologyReportJob::class, fn (GenerateExamPsychologyReportJob $job) => $job->attemptId === $ctx['attempt']->id);
    }

    public function test_submit_succeeds_when_the_queue_is_down(): void
    {
        // Redis queue pointing at a closed port: every push fails.
        config([
            'queue.default' => 'redis',
            'database.redis.client' => 'predis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
        ]);
        $this->app->forgetInstance('redis');
        Redis::clearResolvedInstances();

        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        $submitted = app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);

        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        $this->assertSame('failed', $ctx['attempt']->fresh()->psychology_report_status);
    }

    public function test_mode_ujian_submit_skips_the_report_weakness_stats_xp_and_coins(): void
    {
        Queue::fake();
        $this->mock(ExamWeaknessAnalysisService::class)->shouldNotReceive('forget');
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $submitted = app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);

        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame('skipped', $submitted->psychology_report_status);
        $this->assertNotNull($submitted->total_score);
        Queue::assertNothingPushed();
        $this->assertFalse(XpReward::query()->where('user_id', $ctx['user']->id)->exists());
        $this->assertFalse(CoinTransaction::query()->where('user_id', $ctx['user']->id)->exists());
    }

    public function test_a_regular_submit_still_awards_xp_and_coins(): void
    {
        Queue::fake();
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);

        $this->assertTrue(XpReward::query()->where('user_id', $ctx['user']->id)->exists());
        $this->assertTrue(CoinTransaction::query()->where('user_id', $ctx['user']->id)->exists());
    }

    public function test_a_cache_failure_while_clearing_weakness_stats_does_not_undo_the_submit(): void
    {
        Queue::fake();
        $this->mock(ExamWeaknessAnalysisService::class)
            ->shouldReceive('forget')->once()->andThrow(new RuntimeException('Redis timeout'));
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        DB::transaction(function () use ($ctx) {
            app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);
        });

        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        Queue::assertPushed(GenerateExamPsychologyReportJob::class);
    }
}
