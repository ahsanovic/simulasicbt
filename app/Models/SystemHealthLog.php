<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of the readiness checks from the admin "Kesehatan Sistem" page,
 * kept as history and printable as a report. Only the newest KEEP runs stay.
 */
class SystemHealthLog extends Model
{
    public const KEEP = 100;

    protected $fillable = ['user_id', 'status', 'passed', 'warnings', 'failures', 'results', 'metrics'];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'metrics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function prune(): void
    {
        $cutoff = static::query()->latest('id')->skip(self::KEEP)->value('id');

        if ($cutoff !== null) {
            static::query()->where('id', '<=', $cutoff)->delete();
        }
    }
}
