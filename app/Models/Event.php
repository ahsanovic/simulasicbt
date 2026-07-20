<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'exam_id',
        'status',
        'public_livescore',
        'public_code',
        'starts_at',
        'ends_at',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'public_livescore' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * URL-safe code used for the public livescore link (/livescore/{public_code}).
     */
    public static function generatePublicCode(int $length = 8): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::query()->where('public_code', $code)->exists());

        return $code;
    }

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->public_code ??= static::generatePublicCode();
        });
    }
}
