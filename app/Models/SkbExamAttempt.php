<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Answered / correct counts for many SKB attempts at once (livescore
     * boards), from two aggregate queries instead of loading every answer as
     * a model. "benar" counts saved picks whose option is the correct one —
     * the same rule as SkbExamService::liveScores().
     *
     * @param  list<int>  $attemptIds
     * @return array<int, array{total: int, answered: int, benar: int}>
     */
    public static function liveBoardStats(array $attemptIds): array
    {
        $stats = [];

        foreach ($attemptIds as $id) {
            $stats[$id] = ['total' => 0, 'answered' => 0, 'benar' => 0];
        }

        if ($attemptIds === []) {
            return $stats;
        }

        DB::table('skb_exam_answers')
            ->whereIn('skb_exam_attempt_id', $attemptIds)
            ->groupBy('skb_exam_attempt_id')
            ->selectRaw('skb_exam_attempt_id, COUNT(*) as total, COUNT(selected_option_id) as answered')
            ->get()
            ->each(function (object $row) use (&$stats): void {
                $stats[$row->skb_exam_attempt_id]['total'] = (int) $row->total;
                $stats[$row->skb_exam_attempt_id]['answered'] = (int) $row->answered;
            });

        DB::table('skb_exam_answers as a')
            ->join('skb_question_options as o', 'o.id', '=', 'a.selected_option_id')
            ->whereIn('a.skb_exam_attempt_id', $attemptIds)
            ->where('o.is_correct', true)
            ->groupBy('a.skb_exam_attempt_id')
            ->selectRaw('a.skb_exam_attempt_id, COUNT(*) as benar')
            ->get()
            ->each(function (object $row) use (&$stats): void {
                $stats[$row->skb_exam_attempt_id]['benar'] = (int) $row->benar;
            });

        return $stats;
    }
}
