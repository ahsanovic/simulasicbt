<?php

namespace App\Enums;

enum QuestionOptionContentType: string
{
    case Text = 'text';
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Image => 'Gambar',
        };
    }
}
