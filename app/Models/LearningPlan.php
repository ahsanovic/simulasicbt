<?php

namespace App\Models;

use App\Enums\LearningPlanPriority;
use App\Enums\LearningPlanStatus;
use App\Enums\LearningPlanTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPlan extends Model
{
    public const MAX_ACTIVE_PLANS = 10;

    public const COLORS = [
        'indigo' => ['bg' => 'bg-indigo-500', 'soft' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'ring' => 'ring-indigo-200'],
        'sky' => ['bg' => 'bg-sky-500', 'soft' => 'bg-sky-50', 'text' => 'text-sky-700', 'ring' => 'ring-sky-200'],
        'emerald' => ['bg' => 'bg-emerald-500', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-200'],
        'amber' => ['bg' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-800', 'ring' => 'ring-amber-200'],
        'rose' => ['bg' => 'bg-rose-500', 'soft' => 'bg-rose-50', 'text' => 'text-rose-700', 'ring' => 'ring-rose-200'],
        'violet' => ['bg' => 'bg-violet-500', 'soft' => 'bg-violet-50', 'text' => 'text-violet-700', 'ring' => 'ring-violet-200'],
        'teal' => ['bg' => 'bg-teal-500', 'soft' => 'bg-teal-50', 'text' => 'text-teal-700', 'ring' => 'ring-teal-200'],
        'orange' => ['bg' => 'bg-orange-500', 'soft' => 'bg-orange-50', 'text' => 'text-orange-700', 'ring' => 'ring-orange-200'],
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'priority',
        'status',
        'color',
        'starts_at',
        'ends_at',
        'sort_order',
        'source_evaluation_hash',
    ];

    protected function casts(): array
    {
        return [
            'priority' => LearningPlanPriority::class,
            'status' => LearningPlanStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(LearningPlanTask::class)->orderBy('sort_order');
    }

    public function rootTasks(): HasMany
    {
        return $this->hasMany(LearningPlanTask::class)
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function progressPercent(): int
    {
        $roots = $this->rootTasks;

        if ($roots->isEmpty()) {
            return 0;
        }

        $done = $roots->where('status', LearningPlanTaskStatus::Done)->count();

        return (int) round(($done / $roots->count()) * 100);
    }

    public function colorClasses(): array
    {
        return self::COLORS[$this->color] ?? self::COLORS['indigo'];
    }
}
