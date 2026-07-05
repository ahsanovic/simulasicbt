<?php

namespace App\Http\Middleware;

use App\Services\DuelPresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPesertaPresence
{
    public function __construct(
        private readonly DuelPresenceService $presence,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPeserta()) {
            $this->presence->touch($request->user());
        }

        return $next($request);
    }
}
