<?php

namespace App\Enums;

enum GhostRaceTier: string
{
    case FormationFull = 'formation_full';
    case FormationSparse = 'formation_sparse';
    case NoFormation = 'no_formation';

    public function label(): string
    {
        return match ($this) {
            self::FormationFull => 'Formasi',
            self::FormationSparse => 'Data Terbatas',
            self::NoFormation => 'Mode Latihan',
        };
    }
}
