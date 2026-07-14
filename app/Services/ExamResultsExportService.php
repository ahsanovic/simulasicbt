<?php

namespace App\Services;

use App\Data\ExamResultsExportFilters;
use App\Enums\ExportRequestStatus;
use App\Jobs\ExportExamResultsJob;
use App\Models\ExportRequest;
use App\Models\User;
use App\Support\ExamResultsCsvMapper;
use App\Support\ExamResultsQuery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExamResultsExportService
{
    public function requestExport(User $user, ExamResultsExportFilters $filters): ExportRequest
    {
        $hasActiveExport = ExportRequest::query()
            ->where('user_id', $user->id)
            ->where('type', ExportRequest::TYPE_EXAM_RESULTS_SUMMARY)
            ->whereIn('status', [
                ExportRequestStatus::Pending,
                ExportRequestStatus::Processing,
            ])
            ->exists();

        if ($hasActiveExport) {
            throw new RuntimeException('Masih ada export hasil ujian yang sedang diproses. Tunggu hingga selesai.');
        }

        $totalRows = $this->countRows($filters);

        if ($totalRows === 0) {
            throw new RuntimeException('Tidak ada data hasil ujian untuk diekspor dengan filter saat ini.');
        }

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            'status' => ExportRequestStatus::Pending,
            'filters' => $filters->toArray(),
            'total_rows' => $totalRows,
        ]);

        ExportExamResultsJob::dispatch($exportRequest->id);

        return $exportRequest;
    }

    public function process(ExportRequest $exportRequest): void
    {
        if ($exportRequest->type !== ExportRequest::TYPE_EXAM_RESULTS_SUMMARY) {
            return;
        }

        $exportRequest->markProcessing();

        $filters = $exportRequest->filtersDto();
        $directory = 'exports/exam-results/'.$exportRequest->id;
        $fileName = 'hasil-ujian-'.now()->format('Y-m-d-His').'.csv';
        $relativePath = $directory.'/'.$fileName;
        $absolutePath = Storage::disk('local')->path($relativePath);

        Storage::disk('local')->makeDirectory($directory);

        try {
            $this->writeCsvFile($filters, $absolutePath);

            if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
                throw new RuntimeException('File export tidak berhasil dibuat atau kosong.');
            }

            $totalRows = $this->countRows($filters);

            $exportRequest->markCompleted($relativePath, $fileName, $totalRows);
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            $message = $this->resolveExportErrorMessage($exception);

            $exportRequest->markFailed($message);

            Log::error('Export hasil ujian gagal.', [
                'export_request_id' => $exportRequest->id,
                'filters' => $exportRequest->filters,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function countRows(ExamResultsExportFilters $filters): int
    {
        return ExamResultsQuery::filtered($filters)->count();
    }

    public function cleanupExpired(): int
    {
        $removed = 0;

        ExportRequest::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exportRequests) use (&$removed) {
                foreach ($exportRequests as $exportRequest) {
                    if ($exportRequest->file_path) {
                        $directory = Str::beforeLast($exportRequest->file_path, '/');

                        if ($directory !== $exportRequest->file_path) {
                            Storage::disk('local')->deleteDirectory($directory);
                        } else {
                            Storage::disk('local')->delete($exportRequest->file_path);
                        }
                    }

                    $exportRequest->delete();
                    $removed++;
                }
            });

        return $removed;
    }

    public function resolveExportErrorMessage(Throwable $exception): string
    {
        if (config('app.debug')) {
            return Str::limit($exception->getMessage(), 500);
        }

        $message = $exception->getMessage();

        return match (true) {
            $exception instanceof QueryException => 'Gagal membaca data hasil ujian dari database.',
            str_contains($message, 'Permission denied'),
            str_contains($message, 'failed to open stream') => 'Folder penyimpanan export tidak dapat ditulis. Periksa izin storage/app/private.',
            default => 'Gagal membuat file export hasil ujian.',
        };
    }

    private function writeCsvFile(ExamResultsExportFilters $filters, string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'w');

        if ($handle === false) {
            throw new RuntimeException("Tidak dapat membuka file export: {$absolutePath}");
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");

            $mapper = new ExamResultsCsvMapper;
            fputcsv($handle, $mapper->headings());

            $query = ExamResultsQuery::filtered($filters)
                ->with(['user.instansi', 'exam', 'duelSession'])
                ->orderBy('submitted_at')
                ->orderBy('id');

            foreach ($query->lazy(1000) as $attempt) {
                fputcsv($handle, $mapper->map($attempt));
            }
        } finally {
            fclose($handle);
        }
    }
}
