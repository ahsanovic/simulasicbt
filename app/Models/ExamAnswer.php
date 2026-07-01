<?php

namespace App\Models;

use App\Enums\AnswerReviewOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'sort_order',
        'selected_option_id',
        'is_marked',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_marked' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }

    public function reviewOutcome(): AnswerReviewOutcome
    {
        if (! $this->selected_option_id || ! $this->selectedOption) {
            return AnswerReviewOutcome::Unanswered;
        }

        $question = $this->question;
        $option = $this->selectedOption;

        if ($question->usesWeightedScoring()) {
            return (int) $option->score_weight === $question->maxScoreWeight()
                ? AnswerReviewOutcome::Optimal
                : AnswerReviewOutcome::Suboptimal;
        }

        return $option->is_correct
            ? AnswerReviewOutcome::Correct
            : AnswerReviewOutcome::Incorrect;
    }

    public function earnedPoints(): int
    {
        if (! $this->selectedOption) {
            return 0;
        }

        $subjectCode = $this->question->subject->code;

        return $subjectCode->pointsFromSelectedOption(
            $this->selectedOption->score_weight,
            $this->selectedOption->is_correct,
        );
    }
}
