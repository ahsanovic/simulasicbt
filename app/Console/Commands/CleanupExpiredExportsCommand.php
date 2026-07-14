<?php

namespace App\Console\Commands;

use App\Services\ExamResultsExportService;
use Illuminate\Console\Command;

class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'exports:cleanup';

    protected $description = 'Hapus file export hasil ujian yang sudah kedaluwarsa';

    public function handle(ExamResultsExportService $exportService): int
    {
        $removed = $exportService->cleanupExpired();

        $this->info("Berhasil membersihkan {$removed} export kedaluwarsa.");

        return self::SUCCESS;
    }
}
