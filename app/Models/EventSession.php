<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Support\LiveScoreCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EventSession extends Model
{
    use SoftDeletes;

    /** Roster/session changes show on the livescore boards straight away (see LiveScoreCache). */
    protected static function booted(): void
    {
        static::saved(fn (EventSession $session) => LiveScoreCache::bust($session->event_id));
        static::deleted(fn (EventSession $session) => LiveScoreCache::bust($session->event_id));
    }

    protected $fillable = [
        'event_id',
        'name',
        'code',
        'skd_pin',
        'skb_pin',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function skbAttempts(): HasMany
    {
        return $this->hasMany(SkbExamAttempt::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function isJoinable(): bool
    {
        if ($this->status !== EventStatus::Active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public static function generateUniqueCode(int $length = 6): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Auto-generates a numeric PIN unique across every session's $column
     * (checked globally, not just within one event) — admins never type
     * these, only the system generates them, so uniqueness is enforced here
     * rather than relying on manual entry.
     */
    public static function generateUniquePin(string $column, int $length = 4): string
    {
        do {
            $pin = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        } while (static::query()->where($column, $pin)->exists());

        return $pin;
    }
}
