<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkbQuestion extends Model
{
    use SoftDeletes;

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
