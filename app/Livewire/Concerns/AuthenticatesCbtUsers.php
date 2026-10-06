<?php

namespace App\Livewire\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ModeUjianPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Username/NIP/NIK + password login shared by the simulasi login page and the
 * Mode Sedang Ujian login page. Expects `string $login` and `string $password`.
 */
trait AuthenticatesCbtUsers
{
    protected function validateCredentials(): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'username atau nip harus diisi',
            'login.string' => 'username atau nip harus berupa string',
            'password.required' => 'password harus diisi',
            'password.string' => 'password harus berupa string',
        ]);
    }

    /**
     * The active account matching the credentials. Admins match on username
     * only; peserta on username, NIP or NIK. Counts a failed attempt.
     */
    protected function resolveUserFromCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()
            ->where('is_active', true)
            ->where('username', $this->login)
            ->where('role', UserRole::Admin)
            ->first();

        if ($user === null) {
            // username OR nip OR nik as three indexed lookups joined on the
            // primary key: a single OR over the three columns made MySQL read
            // the whole users table on every login (e.g. an 18-digit NIP is
            // longer than the nik column, which ruled out the index merge).
            $matches = User::query()->select('id')->where('username', $this->login)
                ->union(User::query()->select('id')->where('nip', $this->login))
                ->union(User::query()->select('id')->where('nik', $this->login));

            $user = User::query()
                ->joinSub($matches, 'login_matches', 'login_matches.id', '=', 'users.id')
                ->select('users.*')
                ->where('users.is_active', true)
                ->where('users.role', UserRole::Peserta)
                ->orderBy('users.id')
                ->first();
        }

        if ($user === null || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'kredensial tidak valid atau akun nonaktif.',
            ]);
        }

        return $user;
    }

    /**
     * Keep the stored hash at the cost of the page the user logged in on:
     * Mode Ujian participants on the exam login get the cheaper Mode Ujian
     * cost (ModeUjianPassword), everyone else the application default. The
     * typed password was just verified, so it can be re-hashed.
     */
    protected function rehashPassword(User $user, bool $modeUjianLogin): void
    {
        $options = $modeUjianLogin && $user->isPeserta() ? ['rounds' => ModeUjianPassword::rounds()] : [];

        if (Hash::needsRehash($user->password, $options)) {
            $user->forceFill(['password' => Hash::make($this->password, $options)])->saveQuietly();
        }
    }

    protected function completeLogin(User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectAfterLogin();
    }

    protected function redirectAfterLogin(): void
    {
        $this->redirect(Auth::user()->homeUrl(), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }
}
