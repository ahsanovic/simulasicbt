<?php

namespace App\Models;

use App\Support\ExamQuestionCache;
use App\Support\QuestionPool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkbQuestion extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        // Shown content or the active bank changed: drop the cached copy used
        // by the exam rooms and the question pools exams draw from.
        $forget = function (SkbQuestion $question): void {
            ExamQuestionCache::forgetSkb($question->id);
            QuestionPool::bust('skb');
        };
        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
    }

    protected $fillable = [
        'jabatan_skb_id',
        'content',
        'explanation',
        'difficulty',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(SkbQuestionOption::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function correctOption(): ?SkbQuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }
}
