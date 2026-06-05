<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPeserta
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::Peserta) {
            abort(403, 'Akses ditolak. Hanya peserta yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
