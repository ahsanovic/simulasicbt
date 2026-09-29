<?php

namespace App\Models;

use App\Enums\QuestionOptionContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkbQuestionOption extends Model
{
    protected $fillable = [
        'skb_question_id',
        'label',
        'content_type',
        'content',
        'image_path',
        'is_correct',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => QuestionOptionContentType::class,
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SkbQuestion::class, 'skb_question_id');
    }
}
