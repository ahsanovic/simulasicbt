<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()->where('email', $googleUser->getEmail())->first();

        if ($user) {
            if ($user->isAdmin()) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Akun admin harus masuk menggunakan email/username dan password.');
            }

            if ($user->is_pegawai) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Akun pegawai harus masuk menggunakan email dan password.');
            }

            $user->update([
                'google_id' => $googleUser->getId(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::query()->create([
                'name' => $googleUser->getName() ?? 'Peserta',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'role' => UserRole::Peserta,
                'is_pegawai' => false,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => null,
            ]);
        }

        if (! $user->is_active) {
            return redirect()
                ->route('login')
                ->with('error', 'Akun Anda nonaktif. Hubungi administrator.');
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->route('peserta.dashboard');
    }
}
