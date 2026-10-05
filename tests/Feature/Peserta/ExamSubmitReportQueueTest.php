<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Jobs\GenerateExamPsychologyReportJob;
use App\Services\ExamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * The psychology report job is queued only once the submit is committed, and
 * a queue (Redis) outage never fails or undoes the submit itself.
 */
class ExamSubmitReportQueueTest extends ExamDeadlineEnforcementTest
{
    public function test_report_job_is_queued_only_after_the_submit_commits(): void
    {
        Queue::fake();
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

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

        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $submitted = app(ExamService::class)->submitAttempt($ctx['attempt'], $ctx['user']);

        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        $this->assertSame('failed', $ctx['attempt']->fresh()->psychology_report_status);
    }
}
