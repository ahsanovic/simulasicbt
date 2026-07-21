<?php

namespace App\Enums;

enum ExamHistoryFilter: string
{
    case All = 'all';
    case Full = 'full';
    case Duel = 'duel';
    case Drill = 'drill';
    case Remedial = 'remedial';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua',
            self::Full => 'Simulasi Penuh',
            self::Duel => 'Duel',
            self::Drill => 'Drill Soal',
            self::Remedial => 'Remedial',
            self::Event => 'Event Offline',
        };
    }

    /** @return list<self> */
    public static function options(): array
    {
        return [
            self::All,
            self::Full,
            self::Duel,
            self::Drill,
            self::Remedial,
            self::Event,
        ];
    }
}
