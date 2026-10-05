<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamPreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_failures_without_crashing_when_not_on_redis(): void
    {
        config(['cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync']);

        $this->artisan('exam:preflight')
            ->expectsOutputToContain('CACHE_STORE = redis')
            ->expectsOutputToContain('Migrasi answer_version sudah dijalankan')
            ->expectsOutputToContain('Worker queue memproses job')
            ->expectsOutputToContain('Tulis-baca session')
            ->assertFailed();
    }
}
