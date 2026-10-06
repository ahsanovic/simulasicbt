<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Password hashing for Mode Ujian participant accounts.
 *
 * Their password is their NIK by design (set on import / manual add), so a
 * high bcrypt cost protects nothing a leaked NIK would not already give away,
 * while every participant logs in within the same minutes. These accounts use
 * a cheaper cost (4x less CPU per login and per imported row than the default
 * 12); admins and regular simulasi accounts keep BCRYPT_ROUNDS.
 */
final class ModeUjianPassword
{
    public const ROUNDS = 10;

    /** Never above the configured cost: Laravel refuses to store such a hash. */
    public static function rounds(): int
    {
        return min(self::ROUNDS, (int) config('hashing.bcrypt.rounds', 12));
    }

    public static function hash(string $password): string
    {
        return Hash::make($password, ['rounds' => self::rounds()]);
    }

    public static function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash, ['rounds' => self::rounds()]);
    }
}
