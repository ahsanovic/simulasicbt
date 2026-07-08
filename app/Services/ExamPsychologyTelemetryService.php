<?php

namespace App\Services;

use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamTelemetry;
use App\Models\QuestionOption;

class ExamPsychologyTelemetryService
{
    /**
     * @param  array<string, int>  $questionDurations
     * @param  array<string, array{first_option_id: ?int, change_count: int, last_change_remaining_seconds: ?int}>  $answerBehavior
     */
    public function persistForAttempt(
        ExamAttempt $attempt,
        array $questionDurations,
        array $answerBehavior,
        int $remainingSeconds,
    ): void {
        $attempt->loadMissing(['answers.question.options', 'answers.selectedOption']);

        foreach ($attempt->answers as $answer) {
            $sortOrder = (string) $answer->sort_order;
            $behavior = $answerBehavior[$sortOrder] ?? null;
            $timeSpent = max(0, (int) ($questionDurations[$sortOrder] ?? 0));

            if ($timeSpent === 0 && $behavior === null) {
                continue;
            }

            $firstOptionId = $behavior['first_option_id'] ?? $answer->selected_option_id;
            $changeCount = (int) ($behavior['change_count'] ?? 0);
            $lastChangeRemaining = $behavior['last_change_remaining_seconds'] ?? null;

            $isChangedAtLastMinute = $changeCount > 0
                && $lastChangeRemaining !== null
                && $lastChangeRemaining <= ExamTelemetry::PANIC_WINDOW_SECONDS;

            $changedFromCorrectToWrong = false;

            if ($firstOptionId && $answer->selected_option_id && $firstOptionId !== $answer->selected_option_id) {
                $firstPositive = $this->isPositiveAnswer($answer, (int) $firstOptionId);
                $finalPositive = $answer->reviewOutcome()->isPositive();
                $changedFromCorrectToWrong = $firstPositive && ! $finalPositive;
            }

            ExamTelemetry::query()->updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'question_number' => (int) $answer->sort_order,
                ],
                [
                    'time_spent_seconds' => $timeSpent,
                    'is_changed_at_last_minute' => $isChangedAtLastMinute,
                    'changed_from_correct_to_wrong' => $changedFromCorrectToWrong,
                    'remaining_time_seconds' => $lastChangeRemaining ?? $remainingSeconds,
                ],
            );
        }
    }

    public function isPositiveAnswer(ExamAnswer $answer, int $optionId): bool
    {
        if (! $answer->question) {
            return false;
        }

        $option = $answer->question->options->firstWhere('id', $optionId);

        if (! $option instanceof QuestionOption) {
            return false;
        }

        if ($answer->question->usesWeightedScoring()) {
            return (int) $option->score_weight === $answer->question->maxScoreWeight();
        }

        return (bool) $option->is_correct;
    }
}
