<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs a warning for every request slower than exam.slow_request_ms, so a
 * lag during an exam leaves evidence of which step was slow (shown on the
 * admin "Kesehatan Sistem" page). Only names and numbers are logged: route,
 * Livewire component:method, duration and query count, never the payload.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $thresholdMs = (int) config('exam.slow_request_ms');

        if ($thresholdMs <= 0) {
            return $next($request);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $response = $next($request);
        $ms = (int) round((microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT'))) * 1000);

        if ($ms >= $thresholdMs) {
            Log::warning('Slow request', [
                'ms' => $ms,
                'action' => $this->action($request),
                'queries' => $queries,
                'status' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }

    /** "GET peserta.exam.room" or, for Livewire calls, "peserta.exam-room:next". */
    private function action(Request $request): string
    {
        if ($request->hasHeader('X-Livewire') && is_array($request->input('components'))) {
            return collect($request->input('components'))->map(function ($component) {
                $name = json_decode((string) ($component['snapshot'] ?? ''), true)['memo']['name'] ?? '?';
                $calls = collect($component['calls'] ?? [])->pluck('method')->filter()->implode(',');

                return $calls === '' ? $name : "{$name}:{$calls}";
            })->implode(' ');
        }

        return $request->method().' '.($request->route()?->getName() ?? $request->path());
    }
}
