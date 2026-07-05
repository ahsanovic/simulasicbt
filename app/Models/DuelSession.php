<?php

namespace App\Models;

use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelSession extends Model
{
    public const TOTAL_QUESTIONS = 15;

    public const DURATION_MINUTES = 10;

    protected $fillable = [
        'code',
        'host_user_id',
        'opponent_user_id',
        'is_bot_opponent',
        'question_ids',
        'status',
        'match_type',
        'duration_minutes',
        'started_at',
        'expires_at',
        'host_attempt_id',
        'opponent_attempt_id',
        'host_progress',
        'opponent_progress',
        'host_finished_at',
        'opponent_finished_at',
        'winner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'status' => DuelSessionStatus::class,
            'match_type' => DuelMatchType::class,
            'is_bot_opponent' => 'boolean',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'host_finished_at' => 'datetime',
            'opponent_finished_at' => 'datetime',
            'host_progress' => 'integer',
            'opponent_progress' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function hostAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'host_attempt_id');
    }

    public function opponentAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'opponent_attempt_id');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->host_user_id === $userId
            || $this->opponent_user_id === $userId;
    }

    public function opponentLabelFor(int $userId): string
    {
        if ($this->is_bot_opponent) {
            return 'AI Shadow Bot';
        }

        if ($userId === $this->host_user_id) {
            return $this->opponent?->name ?? 'Lawan';
        }

        return $this->host?->name ?? 'Lawan';
    }

    public function progressFor(int $userId): int
    {
        return $userId === $this->host_user_id
            ? $this->host_progress
            : $this->opponent_progress;
    }

    public function opponentProgressFor(int $userId): int
    {
        return $userId === $this->host_user_id
            ? $this->opponent_progress
            : $this->host_progress;
    }

    public function attemptFor(int $userId): ?ExamAttempt
    {
        if ($userId === $this->host_user_id) {
            return $this->hostAttempt;
        }

        if ($userId === $this->opponent_user_id) {
            return $this->opponentAttempt;
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === DuelSessionStatus::InProgress
            && $this->expires_at !== null
            && now()->lt($this->expires_at);
    }
}
