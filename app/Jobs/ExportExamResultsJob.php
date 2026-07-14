<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Services\ExamResultsExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportExamResultsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public int $exportRequestId,
    ) {}

    public function handle(ExamResultsExportService $exportService): void
    {
        $exportRequest = ExportRequest::query()->find($this->exportRequestId);

        if (! $exportRequest) {
            return;
        }

        $exportService->process($exportRequest);
    }

    public function failed(?Throwable $exception): void
    {
        $exportRequest = ExportRequest::query()->find($this->exportRequestId);

        if (! $exportRequest || ! $exportRequest->status->isActive()) {
            return;
        }

        $message = $exception
            ? app(ExamResultsExportService::class)->resolveExportErrorMessage($exception)
            : 'Export gagal diproses oleh queue worker.';

        $exportRequest->markFailed($message);
    }
}
