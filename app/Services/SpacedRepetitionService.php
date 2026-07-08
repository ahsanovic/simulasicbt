<?php

namespace App\Services;

use App\Enums\FlashcardRating;
use App\Models\Flashcard;

class SpacedRepetitionService
{
    /** @var list<int> */
    public const INTERVALS = [1, 3, 7, 14, 30, 60];

    public function intervalForRepetition(int $repetitionCount): int
    {
        $index = max(0, min($repetitionCount, count(self::INTERVALS) - 1));

        return self::INTERVALS[$index];
    }

    public function applyRating(Flashcard $card, FlashcardRating $rating): Flashcard
    {
        if ($rating === FlashcardRating::Forgot) {
            $card->forget_count++;
            $card->repetition_count = 0;
            $card->interval_days = self::INTERVALS[0];
        } elseif ($rating === FlashcardRating::Partial) {
            $card->repetition_count = max(1, $card->repetition_count);
            $card->interval_days = self::INTERVALS[1];
        } else {
            $card->repetition_count++;
            $card->interval_days = $this->intervalForRepetition($card->repetition_count);
        }

        $card->last_reviewed_at = now();
        $card->next_review_at = now()->addDays($card->interval_days);
        $card->save();

        return $card->fresh();
    }
}
