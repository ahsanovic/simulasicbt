<?php

namespace App\Models;

use App\DTOs\DrillConfig;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamHistoryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id',
        'event_id',
        'event_session_id',
        'duel_session_id',
        'attempt_type',
        'parent_attempt_id',
        'drill_config',
        'user_id',
        'started_at',
        'submitted_at',
        'expires_at',
        'status',
        'score_twk',
        'score_tiu',
        'score_tkp',
        'total_score',
        'question_duration',
        'answer_behavior',
        'help_items_state',
        'stress_test_enabled',
        'stress_test_telemetry',
        'stress_test_analysis',
        'psychology_report',
        'psychology_report_status',
        'psychology_report_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempt_type' => ExamAttemptType::class,
            'status' => ExamAttemptStatus::class,
            'score_twk' => 'integer',
            'score_tiu' => 'integer',
            'score_tkp' => 'integer',
            'total_score' => 'integer',
            'question_duration' => 'array',
            'answer_behavior' => 'array',
            'help_items_state' => 'array',
            'drill_config' => 'array',
            'stress_test_enabled' => 'boolean',
            'stress_test_telemetry' => 'array',
            'stress_test_analysis' => 'array',
            'psychology_report_generated_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function duelSession(): BelongsTo
    {
        return $this->belongsTo(DuelSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentAttempt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_attempt_id');
    }

    public function remedialAttempts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_attempt_id');
    }

    public function isRemedial(): bool
    {
        return $this->attempt_type === ExamAttemptType::Remedial;
    }

    public function isFull(): bool
    {
        return $this->attempt_type === ExamAttemptType::Full;
    }

    public function isDrill(): bool
    {
        return $this->attempt_type === ExamAttemptType::Drill;
    }

    public function drillConfig(): ?DrillConfig
    {
        if (! $this->isDrill() || ! is_array($this->drill_config)) {
            return null;
        }

        return DrillConfig::fromArray($this->drill_config);
    }

    public function historyCategory(): ExamHistoryFilter
    {
        if ($this->isDrill()) {
            return ExamHistoryFilter::Drill;
        }

        if ($this->isRemedial()) {
            return ExamHistoryFilter::Remedial;
        }

        if ($this->isDuelAttempt()) {
            return ExamHistoryFilter::Duel;
        }

        if ($this->event_id !== null) {
            return ExamHistoryFilter::Event;
        }

        return ExamHistoryFilter::Full;
    }

    public function displayTitle(): string
    {
        if ($this->event) {
            return $this->event->name;
        }

        if ($this->isDrill()) {
            $config = $this->drillConfig();

            if ($config !== null) {
                return 'Drill '.$config->subjectCode->label();
            }

            return 'Drill Soal';
        }

        return $this->exam?->title ?? 'Ujian';
    }

    public function scopeDrill(Builder $query): Builder
    {
        return $query->where('attempt_type', ExamAttemptType::Drill);
    }

    public function scopeRemedial(Builder $query): Builder
    {
        return $query->where('attempt_type', ExamAttemptType::Remedial);
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('attempt_type', ExamAttemptType::Full);
    }

    public function scopeForHistoryFilter(Builder $query, ExamHistoryFilter $filter): Builder
    {
        return match ($filter) {
            ExamHistoryFilter::All => $query,
            ExamHistoryFilter::Full => $query
                ->where('attempt_type', ExamAttemptType::Full)
                ->whereNull('duel_session_id')
                ->whereNull('event_id'),
            ExamHistoryFilter::Duel => $query->where(function (Builder $builder) {
                $builder->whereNotNull('duel_session_id')
                    ->orWhereHas('exam', fn (Builder $examQuery) => $examQuery->where('settings->is_duel', true));
            }),
            ExamHistoryFilter::Drill => $query->where('attempt_type', ExamAttemptType::Drill),
            ExamHistoryFilter::Remedial => $query->where('attempt_type', ExamAttemptType::Remedial),
            ExamHistoryFilter::Event => $query->whereNotNull('event_id'),
        };
    }

    public function isDuelAttempt(): bool
    {
        if ($this->duel_session_id !== null) {
            return true;
        }

        return (bool) $this->exam?->isDuel();
    }

    public function scopeFull(Builder $query): Builder
    {
        return $query->where('attempt_type', ExamAttemptType::Full);
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

    public function wrongAnswerCount(): int
    {
        $this->loadMissing(['answers.question', 'answers.selectedOption']);

        return $this->answers
            ->filter(fn (ExamAnswer $answer) => $answer->question && ! $answer->reviewOutcome()->isPositive())
            ->count();
    }

    public function correctAnswerCount(): int
    {
        $this->loadMissing(['answers.question', 'answers.selectedOption']);

        return $this->answers
            ->filter(fn (ExamAnswer $answer) => $answer->question && $answer->reviewOutcome()->isPositive())
            ->count();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('sort_order');
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(ExamTelemetry::class)->orderBy('question_number');
    }

    public function isActive(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress
            && now()->lt($this->expires_at);
    }

    public function remainingSeconds(): int
    {
        if (now()->gte($this->expires_at)) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->expires_at);
    }

    /** @return array{twk: int, tiu: int, tkp: int, total: int} */
    public function calculateScores(): array
    {
        $this->loadMissing(['answers.selectedOption', 'answers.question.subject']);

        $scores = [
            'twk' => 0,
            'tiu' => 0,
            'tkp' => 0,
        ];

        foreach ($this->answers as $answer) {
            if (! $answer->selected_option_id || ! $answer->question?->subject) {
                continue;
            }

            $scores[$answer->question->subject->code->value] += $answer->earnedPoints();
        }

        $scores['total'] = array_sum($scores);

        return $scores;
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [
            ExamAttemptStatus::Submitted,
            ExamAttemptStatus::Expired,
        ], true);
    }

    public function scopeReviewableForUser(Builder $query, int $userId): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->whereIn('status', [
                ExamAttemptStatus::Submitted,
                ExamAttemptStatus::Expired,
            ]);
    }

    public static function findReviewableForUser(int $attemptId, int $userId): self
    {
        return static::query()
            ->reviewableForUser($userId)
            ->with([
                'exam',
                'event:id,name',
                'answers.question.subject',
                'answers.question.options',
                'answers.selectedOption',
            ])
            ->findOrFail($attemptId);
    }
}
