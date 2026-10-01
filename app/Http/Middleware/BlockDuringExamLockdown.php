<?php

namespace App\Http\Middleware;

use App\Support\ExamLockdown;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While Mode Sedang Ujian is on, the simulasi login and every simulasi page
 * show the closure notice instead. Admins and Mode Ujian participants pass.
 */
class BlockDuringExamLockdown
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ExamLockdown::active()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null && ($user->isAdmin() || $user->isActiveModeUjianParticipant())) {
            return $next($request);
        }

        return response()->view('ujian.closed', [
            'reopensAt' => ExamLockdown::reopensAt(),
            'message' => ExamLockdown::message(),
        ]);
    }
}
