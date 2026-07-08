<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamTelemetry extends Model
{
    public const PANIC_WINDOW_SECONDS = 1200;

    public const FAST_SKIM_SECONDS = 10;

    protected $fillable = [
        'exam_attempt_id',
        'question_number',
        'time_spent_seconds',
        'is_changed_at_last_minute',
        'changed_from_correct_to_wrong',
        'remaining_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'is_changed_at_last_minute' => 'boolean',
            'changed_from_correct_to_wrong' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
