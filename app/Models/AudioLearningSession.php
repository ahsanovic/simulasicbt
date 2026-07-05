<?php

namespace App\Models;

use App\Enums\SubjectCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioLearningSession extends Model
{
    protected $fillable = [
        'user_id',
        'subject_code',
        'question_count',
        'xp_earned',
        'duration_seconds',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_code' => SubjectCode::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
