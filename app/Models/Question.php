<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id',
        'material_id',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usesWeightedScoring(): bool
    {
        return $this->subject->code->usesWeightedScoring();
    }

    public function correctOption(): ?QuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function maxScoreWeight(): int
    {
        return (int) ($this->options->max('score_weight') ?? 1);
    }

    public function isKeyOption(QuestionOption $option): bool
    {
        if ($this->usesWeightedScoring()) {
            return (int) $option->score_weight === $this->maxScoreWeight();
        }

        return $option->is_correct;
    }
}
