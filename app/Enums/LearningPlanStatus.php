<?php

namespace App\Enums;

enum LearningPlanStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Completed => 'Selesai',
            self::Archived => 'Diarsipkan',
        };
    }
}
