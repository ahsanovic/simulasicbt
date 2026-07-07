<?php

namespace App\Models;

use App\Enums\TestimonialReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestimonialReaction extends Model
{
    protected $fillable = [
        'testimonial_id',
        'user_id',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => TestimonialReactionType::class,
        ];
    }

    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
