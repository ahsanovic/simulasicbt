<?php

namespace App\Models;

use App\Enums\QuestionImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SkbQuestionImportJob extends Model
{
    protected $fillable = [
        'jabatan_skb_id',
        'user_id',
        'total_rows',
        'processed_rows',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuestionImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function jabatanSkb(): BelongsTo
    {
        return $this->belongsTo(JabatanSkb::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStale(): bool
    {
        if ($this->status !== QuestionImportStatus::Pending) {
            return false;
        }

        return $this->created_at->diffInMinutes(now()) >= 2;
    }

    public function markProcessing(): void
    {
        if ($this->status !== QuestionImportStatus::Pending) {
            return;
        }

        $this->update([
            'status' => QuestionImportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function progressPercent(): int
    {
        if (Cache::has("skb-import-progress-{$this->id}")) {
            return (int) Cache::get("skb-import-progress-{$this->id}");
        }

        if ($this->total_rows === 0) {
            return 0;
        }

        if ($this->status === QuestionImportStatus::Completed) {
            return 100;
        }

        return min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
    }

    public function advance(int $rows): void
    {
        if ($rows <= 0) {
            return;
        }

        if ($this->status === QuestionImportStatus::Pending) {
            $this->markProcessing();
        }

        $this->increment('processed_rows', $rows);

        if ($this->total_rows > 0) {
            $percent = min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
            Cache::put("skb-import-progress-{$this->id}", $percent, now()->addMinutes(10));
        }
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => QuestionImportStatus::Completed,
            'processed_rows' => $this->total_rows,
            'completed_at' => now(),
        ]);

        Cache::forget("skb-import-progress-{$this->id}");
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => QuestionImportStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        Cache::forget("skb-import-progress-{$this->id}");
    }
}
