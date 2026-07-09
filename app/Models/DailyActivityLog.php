<?php

namespace App\Models;

use App\Enums\DailyActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'source_id',
        'activity_date',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => DailyActivityType::class,
            'activity_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
