<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'duration_minutes',
        'starts_at',
        'ends_at',
        'status',
        'pin',
        'settings',
        'pass_score',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ExamStatus::class,
            'settings' => 'array',
            'pass_score' => 'decimal:2',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAvailable(): bool
    {
        if ($this->status !== ExamStatus::Published) {
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

    public function isDuel(): bool
    {
        return (bool) ($this->settings['is_duel'] ?? false);
    }

    public function requiresPin(): bool
    {
        return filled($this->pin);
    }

    /**
     * Generate a random exam PIN combining uppercase letters and digits.
     */
    public static function generatePin(int $length = 4): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $pin = '';

        for ($i = 0; $i < $length; $i++) {
            $pin .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $pin;
    }
}
