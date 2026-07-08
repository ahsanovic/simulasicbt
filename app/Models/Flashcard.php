<?php

namespace App\Models;

use App\Enums\FlashcardSourceType;
use App\Enums\SubjectCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashcard extends Model
{
    public const MAX_ACTIVE_CARDS = 50;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'front',
        'back',
        'subject_code',
        'material_id',
        'interval_days',
        'repetition_count',
        'forget_count',
        'next_review_at',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => FlashcardSourceType::class,
            'subject_code' => SubjectCode::class,
            'next_review_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function isDue(): bool
    {
        return $this->next_review_at->lte(now());
    }

    public function displayTitle(): string
    {
        if ($this->material) {
            return $this->material->displayName();
        }

        return $this->subject_code->label();
    }
}
