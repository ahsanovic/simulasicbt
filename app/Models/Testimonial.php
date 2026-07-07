<?php

namespace App\Models;

use App\Enums\TestimonialFeatureTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Testimonial extends Model
{
    protected $fillable = [
        'user_id',
        'target_instansi',
        'story',
        'rating',
        'turning_point',
        'feature_tags',
        'is_anonymous',
    ];

    protected function casts(): array
    {
        return [
            'feature_tags' => 'array',
            'rating' => 'integer',
            'is_anonymous' => 'boolean',
            'hearts_count' => 'integer',
            'fires_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TestimonialReaction::class);
    }

    public function reactionsScore(): int
    {
        return $this->hearts_count + $this->fires_count;
    }

    /** @return list<TestimonialFeatureTag> */
    public function resolvedFeatureTags(): array
    {
        return collect($this->feature_tags ?? [])
            ->map(fn (string $value) => TestimonialFeatureTag::tryFrom($value))
            ->filter()
            ->values()
            ->all();
    }
}
