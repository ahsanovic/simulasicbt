<?php

namespace App\Enums;

enum DuelSessionStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu Lawan',
            self::InProgress => 'Sedang Berlangsung',
            self::Completed => 'Selesai',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
