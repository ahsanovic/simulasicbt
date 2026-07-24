<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum ScoreTrendPeriod: string
{
    case All = 'all';
    case Days7 = '7d';
    case Days30 = '30d';
    case Days90 = '90d';
    case Days180 = '180d';
    case Year1 = '1y';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua',
            self::Days7 => '7 Hari',
            self::Days30 => '30 Hari',
            self::Days90 => '3 Bulan',
            self::Days180 => '6 Bulan',
            self::Year1 => '1 Tahun',
        };
    }

    public function since(?CarbonInterface $now = null): ?CarbonInterface
    {
        $now ??= now();

        return match ($this) {
            self::All => null,
            self::Days7 => $now->copy()->subDays(7)->startOfDay(),
            self::Days30 => $now->copy()->subDays(30)->startOfDay(),
            self::Days90 => $now->copy()->subDays(90)->startOfDay(),
            self::Days180 => $now->copy()->subDays(180)->startOfDay(),
            self::Year1 => $now->copy()->subYear()->startOfDay(),
        };
    }

    /** @return list<self> */
    public static function options(): array
    {
        return [
            self::All,
            self::Days7,
            self::Days30,
            self::Days90,
            self::Days180,
            self::Year1,
        ];
    }
}
