<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Slow requests leave a "Slow request" warning with names and numbers only,
 * so a lag during an exam can be traced on the Kesehatan Sistem page.
 */
class SlowRequestLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_slow_request_is_logged_with_its_route_and_query_count(): void
    {
        config(['exam.slow_request_ms' => 1]);
        Log::spy();

        $this->get(route('login'))->assertOk();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return $message === 'Slow request'
                && $context['action'] === 'GET login'
                && $context['ms'] >= 1
                && is_int($context['queries'])
                && $context['status'] === 200;
        })->once();
    }

    public function test_a_livewire_call_is_logged_as_component_and_method_without_its_payload(): void
    {
        config(['exam.slow_request_ms' => 1]);
        Log::spy();

        $this->withHeaders(['X-Livewire' => '1'])->get(route('login').'?'.http_build_query(['components' => [[
            'snapshot' => json_encode(['memo' => ['name' => 'peserta.exam-room'], 'data' => ['password' => 'rahasia']]),
            'calls' => [['method' => 'next', 'params' => ['rahasia']]],
        ]]]));

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return $context['action'] === 'peserta.exam-room:next'
                && ! str_contains(json_encode($context), 'rahasia');
        })->once();
    }

    public function test_fast_requests_and_a_disabled_threshold_log_nothing(): void
    {
        Log::spy();

        config(['exam.slow_request_ms' => 60_000]);
        $this->get(route('login'))->assertOk();

        config(['exam.slow_request_ms' => 0]);
        $this->get(route('login'))->assertOk();

        Log::shouldNotHaveReceived('warning');
    }
}
