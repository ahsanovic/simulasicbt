<?php

namespace App\Enums;

enum AnswerReviewOutcome: string
{
    case Unanswered = 'unanswered';
    case Correct = 'correct';
    case Incorrect = 'incorrect';
    case Optimal = 'optimal';
    case Suboptimal = 'suboptimal';

    public function label(): string
    {
        return match ($this) {
            self::Unanswered => 'Tidak dijawab',
            self::Correct => 'Benar',
            self::Incorrect => 'Salah',
            self::Optimal => 'Bobot tertinggi',
            self::Suboptimal => 'Bobot lebih rendah',
        };
    }

    public function isPositive(): bool
    {
        return match ($this) {
            self::Correct, self::Optimal => true,
            default => false,
        };
    }
}
