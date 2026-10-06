<?php

use App\Http\Middleware\BlockDuringExamLockdown;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsPeserta;
use App\Http\Middleware\LogSlowRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$appBasePath = trim((string) env(
    'APP_BASE_PATH',
    parse_url((string) env('APP_URL', ''), PHP_URL_PATH) ?: '',
), '/');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: ($appBasePath !== '' ? '/'.$appBasePath : '').'/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'peserta' => EnsureUserIsPeserta::class,
            'exam-lockdown' => BlockDuringExamLockdown::class,
        ]);

        // A logged-in user opening /login or /ujian/login goes to their own
        // dashboard. Without this the "guest" middleware fell back to "/",
        // which redirects to /login again: an endless redirect loop.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? '/');

        // Evidence for lag during an exam: slow web/Livewire requests are logged.
        $middleware->web(append: [LogSlowRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
