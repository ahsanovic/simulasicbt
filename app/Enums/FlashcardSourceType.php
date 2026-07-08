<?php

namespace App\Enums;

enum FlashcardSourceType: string
{
    case Question = 'question';
    case CheatSheet = 'cheat_sheet';

    public function label(): string
    {
        return match ($this) {
            self::Question => 'Soal',
            self::CheatSheet => 'Cheat-Sheet',
        };
    }
}
