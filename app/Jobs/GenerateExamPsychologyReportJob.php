<?php

namespace App\Jobs;

use App\Models\ExamAttempt;
use App\Services\ExamPsychologyReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateExamPsychologyReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $attemptId,
    ) {}

    public function handle(ExamPsychologyReportService $reportService): void
    {
        $attempt = ExamAttempt::query()->find($this->attemptId);

        if (! $attempt || ! $attempt->isReviewable()) {
            return;
        }

        try {
            $reportService->generateForAttempt($attempt);
        } catch (\Throwable $exception) {
            Log::warning('Gagal membuat rapor psikologi ujian.', [
                'attempt_id' => $this->attemptId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
