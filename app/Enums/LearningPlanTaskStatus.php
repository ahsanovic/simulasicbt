<?php

namespace App\Enums;

enum LearningPlanTaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    public function columnTitle(): string
    {
        return match ($this) {
            self::Todo => 'Belum Dikerjakan',
            self::InProgress => 'Sedang Dikerjakan',
            self::Done => 'Selesai',
        };
    }

    public function columnAccent(): string
    {
        return match ($this) {
            self::Todo => 'border-slate-300 bg-slate-100 text-slate-700',
            self::InProgress => 'border-primary-300 bg-primary-50 text-primary-700',
            self::Done => 'border-emerald-300 bg-emerald-50 text-emerald-700',
        };
    }
}
