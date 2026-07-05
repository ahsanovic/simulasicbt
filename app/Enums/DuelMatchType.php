<?php

namespace App\Enums;

enum DuelMatchType: string
{
    case Friend = 'friend';
    case Random = 'random';
    case Code = 'code';

    public function label(): string
    {
        return match ($this) {
            self::Friend => 'Tantang Teman',
            self::Random => 'Matchmaking Acak',
            self::Code => 'Gabung Kode',
        };
    }
}
