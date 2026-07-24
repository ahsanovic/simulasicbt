<?php

namespace App\Enums;

enum LearningPlanPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Urgent => 'Mendesak',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-600',
            self::Medium => 'bg-sky-100 text-sky-700',
            self::High => 'bg-amber-100 text-amber-800',
            self::Urgent => 'bg-rose-100 text-rose-700',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-400',
            self::Medium => 'bg-sky-500',
            self::High => 'bg-amber-500',
            self::Urgent => 'bg-rose-500',
        };
    }
}
