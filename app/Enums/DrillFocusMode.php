<?php

namespace App\Enums;

enum DrillFocusMode: string
{
    case Weak = 'weak';
    case Random = 'random';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Weak => 'Soal yang sering salah',
            self::Random => 'Acak dari sub-materi',
            self::Mixed => 'Campuran (disarankan)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weak => 'Fokus pada soal yang pernah Anda jawab salah di simulasi penuh.',
            self::Random => 'Soal diacak dari bank sub-materi yang dipilih.',
            self::Mixed => '70% soal sulit Anda + 30% soal acak untuk variasi.',
        };
    }
}
