<?php

namespace App\Models;

use App\Data\ExamResultsExportFilters;
use App\Enums\ExportRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExportRequest extends Model
{
    public const TYPE_EXAM_RESULTS_SUMMARY = 'exam_results_summary';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filters',
        'file_path',
        'file_name',
        'total_rows',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExportRequestStatus::class,
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filtersDto(): ExamResultsExportFilters
    {
        return ExamResultsExportFilters::fromArray($this->filters ?? []);
    }

    public function markProcessing(): void
    {
        if ($this->status !== ExportRequestStatus::Pending) {
            return;
        }

        $this->update([
            'status' => ExportRequestStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(string $filePath, string $fileName, int $totalRows): void
    {
        $this->update([
            'status' => ExportRequestStatus::Completed,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_rows' => $totalRows,
            'completed_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => ExportRequestStatus::Failed,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportRequestStatus::Completed
            && $this->file_path !== null
            && Storage::disk('local')->exists($this->file_path)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isQueueStale(): bool
    {
        if ($this->status !== ExportRequestStatus::Pending) {
            return false;
        }

        return $this->created_at->diffInMinutes(now()) >= 2;
    }
}
