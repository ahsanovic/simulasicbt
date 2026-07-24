<?php

namespace App\Enums;

enum ExamAttemptType: string
{
    case Full = 'full';
    case Remedial = 'remedial';
    case Drill = 'drill';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Simulasi Penuh',
            self::Remedial => 'Ujian Remedial',
            self::Drill => 'Drill Soal',
        };
    }
}
