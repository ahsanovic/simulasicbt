<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Password hashing for Mode Ujian participant accounts.
 *
 * Their password is their NIK by design (set on import / manual add), so a
 * high bcrypt cost protects nothing a leaked NIK would not already give away,
 * while every participant logs in at the same second. These accounts use a
 * cheap cost (16x less CPU per login and per imported row than the default
 * 12); what protects a Mode Ujian exam is the session PIN given out loud by
 * the proctor. Admins and regular simulasi accounts keep BCRYPT_ROUNDS.
 */
final class ModeUjianPassword
{
    public const ROUNDS = 8;

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
