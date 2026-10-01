<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ExamDeadline
{
    /**
     * Seconds past the deadline during which an answer that was already on its
     * way when the clock hit zero is still accepted, so a "Simpan" clicked in
     * the last second isn't lost to network latency. Not extra exam time: the
     * browser locks the screen at zero.
     */
    public const GRACE_SECONDS = 5;

    public static function acceptsAnswers(CarbonInterface $expiresAt): bool
    {
        return now()->lt($expiresAt->copy()->addSeconds(self::GRACE_SECONDS));
    }
}
