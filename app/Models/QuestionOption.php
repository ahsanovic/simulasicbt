<?php

namespace App\Models;

use App\Enums\QuestionOptionContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'label',
        'content_type',
        'content',
        'image_path',
        'is_correct',
        'score_weight',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'content_type' => QuestionOptionContentType::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (QuestionOption $option) {
            if ($option->image_path) {
                Storage::disk('public')->delete($option->image_path);
            }
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function isImage(): bool
    {
        return $this->content_type === QuestionOptionContentType::Image;
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
