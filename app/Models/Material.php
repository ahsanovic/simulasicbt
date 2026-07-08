<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Material extends Model
{
    protected $fillable = [
        'subject_id',
        'material_group_id',
        'name',
        'slug',
        'sort_order',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function materialGroup(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function cheatSheet(): HasOne
    {
        return $this->hasOne(MaterialCheatSheet::class);
    }

    public function scopeOrderedForSelect(Builder $query): Builder
    {
        return $query
            ->leftJoin('subjects', 'materials.subject_id', '=', 'subjects.id')
            ->leftJoin('material_groups', 'materials.material_group_id', '=', 'material_groups.id')
            ->orderBy('subjects.sort_order')
            ->orderByRaw('COALESCE(material_groups.sort_order, 0)')
            ->orderBy('materials.sort_order')
            ->select('materials.*');
    }

    public function displayName(): string
    {
        if ($this->materialGroup) {
            return "{$this->materialGroup->name} — {$this->name}";
        }

        return $this->name;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}
