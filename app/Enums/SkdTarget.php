<?php

namespace App\Enums;

enum SkdTarget: string
{
    case Cpns = 'cpns';
    case SekolahKedinasan = 'sekolah_kedinasan';

    public function label(): string
    {
        return match ($this) {
            self::Cpns => 'CPNS',
            self::SekolahKedinasan => 'Sekolah Kedinasan',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Cpns => 'CPNS',
            self::SekolahKedinasan => 'Kedinasan',
        };
    }

    public static function default(): self
    {
        $configured = config('exam.default_skd_target');

        return self::tryFrom((string) $configured) ?? self::Cpns;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $target) => [
                'value' => $target->value,
                'label' => $target->label(),
            ])
            ->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function filterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua'],
            ...self::options(),
        ];
    }

    /** @return array{twk: int, tiu: int, tkp: int, total: int} */
    public static function minimumPassingGrades(): array
    {
        $profiles = collect(self::cases())
            ->map(fn (self $target) => exam_passing_grades($target));

        return [
            'twk' => (int) $profiles->min('twk'),
            'tiu' => (int) $profiles->min('tiu'),
            'tkp' => (int) $profiles->min('tkp'),
            'total' => (int) $profiles->min('total'),
        ];
    }
}
