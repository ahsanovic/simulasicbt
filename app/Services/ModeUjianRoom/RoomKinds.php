<?php

namespace App\Services\ModeUjianRoom;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RoomKinds
{
    public static function get(string $key): RoomKind
    {
        return match ($key) {
            'skd' => app(SkdRoom::class),
            'skb' => app(SkbRoom::class),
            default => throw new NotFoundHttpException,
        };
    }

    /** Where the Mode Ujian dashboard sends a participant after the session PIN. */
    public static function entryUrl(string $phase, ?int $examId): string
    {
        if (config('exam.client_room')) {
            return route('peserta.mode-ujian.room', $phase);
        }

        return $phase === 'skd' ? route('peserta.exam.room', $examId) : route('peserta.mode-ujian.skb.room');
    }
}
