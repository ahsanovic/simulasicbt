<?php

namespace App\Models;

use App\Enums\LearningPlanPriority;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\LearningPlanTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPlanTask extends Model
{
    protected $fillable = [
        'learning_plan_id',
        'parent_id',
        'title',
        'notes',
        'category',
        'priority',
        'status',
        'scheduled_at',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => LearningPlanTaskCategory::class,
            'priority' => LearningPlanPriority::class,
            'status' => LearningPlanTaskStatus::class,
            'scheduled_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LearningPlan::class, 'learning_plan_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function subtaskProgress(): array
    {
        $total = $this->subtasks->count();

        if ($total === 0) {
            return ['done' => 0, 'total' => 0, 'percent' => 0];
        }

        $done = $this->subtasks->where('status', LearningPlanTaskStatus::Done)->count();

        return [
            'done' => $done,
            'total' => $total,
            'percent' => (int) round(($done / $total) * 100),
        ];
    }

    public function markDone(): void
    {
        $this->forceFill([
            'status' => LearningPlanTaskStatus::Done,
            'completed_at' => now(),
        ])->save();
    }

    public function markOpen(LearningPlanTaskStatus $status = LearningPlanTaskStatus::Todo): void
    {
        $this->forceFill([
            'status' => $status,
            'completed_at' => null,
        ])->save();
    }
}
