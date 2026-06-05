<?php

namespace App\Enums;

enum ExamAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang Berlangsung',
            self::Submitted => 'Selesai',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
