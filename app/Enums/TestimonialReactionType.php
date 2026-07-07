<?php

namespace App\Enums;

enum TestimonialReactionType: string
{
    case Heart = 'heart';
    case Fire = 'fire';

    public function emoji(): string
    {
        return match ($this) {
            self::Heart => '❤️',
            self::Fire => '🔥',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Heart => 'Kirim Energi Positif',
            self::Fire => 'Semangat!',
        };
    }
}
