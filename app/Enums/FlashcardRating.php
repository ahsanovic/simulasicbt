<?php

namespace App\Enums;

enum FlashcardRating: string
{
    case Forgot = 'forgot';
    case Partial = 'partial';
    case Remembered = 'remembered';

    public function label(): string
    {
        return match ($this) {
            self::Forgot => 'Lupa',
            self::Partial => 'Agak ingat',
            self::Remembered => 'Sudah hafal',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Forgot => '😰',
            self::Partial => '🤔',
            self::Remembered => '✅',
        };
    }
}
