<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'recommendation_text',
        'weakness_stats',
        'latest_attempt_at',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'weakness_stats' => 'array',
            'latest_attempt_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
