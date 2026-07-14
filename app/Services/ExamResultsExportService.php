<?php

namespace App\Services;

use App\Data\ExamResultsExportFilters;
use App\Enums\ExportRequestStatus;
use App\Exports\ExamResultsSummaryExport;
use App\Jobs\ExportExamResultsJob;
use App\Models\ExportRequest;
use App\Models\User;
use App\Support\ExamResultsQuery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

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

        Storage::disk('local')->makeDirectory($directory);

        try {
            Excel::store(
                new ExamResultsSummaryExport($filters),
                $relativePath,
                'local',
                ExcelFormat::CSV,
            );

            $totalRows = $this->countRows($filters);

            $exportRequest->markCompleted($relativePath, $fileName, $totalRows);
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            $exportRequest->markFailed('Gagal membuat file export hasil ujian.');

            Log::error('Export hasil ujian gagal.', [
                'export_request_id' => $exportRequest->id,
                'message' => $exception->getMessage(),
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
}
