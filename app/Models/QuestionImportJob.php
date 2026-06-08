<?php

namespace App\Models;

use App\Enums\QuestionImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class QuestionImportJob extends Model
{
    protected $fillable = [
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // public function progressPercent(): int
    // {
    //     if ($this->total_rows === 0) {
    //         return 0;
    //     }

    //     if ($this->status === QuestionImportStatus::Completed) {
    //         return 100;
    //     }

    //     return min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
    // }

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

    // public function advance(int $rows): void
    // {
    //     if ($rows <= 0) {
    //         return;
    //     }

    //     if ($this->status === QuestionImportStatus::Pending) {
    //         $this->markProcessing();
    //     }

    //     $this->increment('processed_rows', $rows);
    // }

    // public function markCompleted(): void
    // {
    //     $this->update([
    //         'status' => QuestionImportStatus::Completed,
    //         'processed_rows' => $this->total_rows,
    //         'completed_at' => now(),
    //     ]);
    // }

    // public function markFailed(string $message): void
    // {
    //     $this->update([
    //         'status' => QuestionImportStatus::Failed,
    //         'error_message' => $message,
    //         'completed_at' => now(),
    //     ]);
    // }

    public function progressPercent(): int
    {
        // KUNCI 1: Cek apakah ada data progress real-time di Cache terlebih dahulu
        if (Cache::has("import-progress-{$this->id}")) {
            return (int) Cache::get("import-progress-{$this->id}");
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

        // KUNCI 2: Hitung persentase saat ini dan lempar langsung ke Cache (Bypass DB Lock)
        if ($this->total_rows > 0) {
            $percent = min(99, (int) round(($this->processed_rows / $this->total_rows) * 100));
            Cache::put("import-progress-{$this->id}", $percent, now()->addMinutes(10));
        }
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => QuestionImportStatus::Completed,
            'processed_rows' => $this->total_rows,
            'completed_at' => now(),
        ]);

        // KUNCI 3: Hapus sampah cache setelah proses import resmi selesai sempurna
        Cache::forget("import-progress-{$this->id}");
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => QuestionImportStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        Cache::forget("import-progress-{$this->id}");
    }
}
