<?php

namespace App\Enums;

enum DailyActivityType: string
{
    case Audio = 'audio';
    case Flashcard = 'flashcard';
    case Duel = 'duel';
    case CheatSheet = 'cheat_sheet';

    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Audio Mode',
            self::Flashcard => 'Kartu Sakti',
            self::Duel => 'Duel Mini-Tryout',
            self::CheatSheet => 'Materi Cheat-Sheet',
        };
    }
}
