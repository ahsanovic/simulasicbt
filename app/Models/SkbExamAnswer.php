<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkbExamAnswer extends Model
{
    protected $fillable = [
        'skb_exam_attempt_id',
        'skb_question_id',
        'sort_order',
        'selected_option_id',
        'is_correct',
        'is_marked',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'is_marked' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SkbExamAttempt::class, 'skb_exam_attempt_id');
    }

    /**
     * Includes soft-deleted questions: an answer always needs its question to
     * be shown and scored, even if the question was removed from the bank
     * after the participant got it.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SkbQuestion::class, 'skb_question_id')->withTrashed();
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(SkbQuestionOption::class, 'selected_option_id');
    }
}
