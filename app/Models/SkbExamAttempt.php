<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkbExamAttempt extends Model
{
    protected $fillable = [
        'event_id',
        'event_session_id',
        'event_participant_id',
        'user_id',
        'jabatan_skb_id',
        'started_at',
        'submitted_at',
        'expires_at',
        'status',
        'correct_score',
        'correct_count',
        'total_score',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => ExamAttemptStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function eventParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SkbExamAnswer::class)->orderBy('sort_order');
    }

    public function isActive(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress && now()->lt($this->expires_at);
    }

    public function remainingSeconds(): int
    {
        return max(0, now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * Still marked as running even though the deadline has passed — these need
     * closing out before their status is reported anywhere.
     */
    public function scopeExpiredButOpen(Builder $query): Builder
    {
        return $query
            ->where('status', ExamAttemptStatus::InProgress)
            ->where('expires_at', '<=', now());
    }
}
