<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'started_at',
        'submitted_at',
        'expires_at',
        'status',
        'score_twk',
        'score_tiu',
        'score_tkp',
        'total_score',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => ExamAttemptStatus::class,
            'score_twk' => 'integer',
            'score_tiu' => 'integer',
            'score_tkp' => 'integer',
            'total_score' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('sort_order');
    }

    public function isActive(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress
            && now()->lt($this->expires_at);
    }
}
