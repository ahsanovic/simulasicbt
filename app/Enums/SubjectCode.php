<?php

namespace App\Enums;

enum SubjectCode: string
{
    case Twk = 'twk';
    case Tiu = 'tiu';
    case Tkp = 'tkp';

    public function label(): string
    {
        return match ($this) {
            self::Twk => 'TWK',
            self::Tiu => 'TIU',
            self::Tkp => 'TKP',
        };
    }

    public function usesWeightedScoring(): bool
    {
        return $this === self::Tkp;
    }

    public function correctAnswerPoints(): int
    {
        return match ($this) {
            self::Twk, self::Tiu => 5,
            self::Tkp => 0,
        };
    }

    public function pointsFromSelectedOption(?int $scoreWeight, bool $isCorrect): int
    {
        if ($this === self::Tkp) {
            $weight = $scoreWeight ?? 1;

            return max(1, min(5, $weight));
        }

        return $isCorrect ? $this->correctAnswerPoints() : 0;
    }
}
