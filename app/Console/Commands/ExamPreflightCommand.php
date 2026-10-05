<?php

namespace App\Console\Commands;

use App\Support\SystemHealth;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Read-only readiness check before a large exam: runs every check against
 * the live configuration and prints LULUS / PERINGATAN / GAGAL, so the server
 * can be verified without a trial run. Exits non-zero when anything fails.
 * The same checks back the admin "Kesehatan Sistem" page.
 */
class ExamPreflightCommand extends Command
{
    protected $signature = 'exam:preflight';

    protected $description = 'Cek kesiapan server sebelum ujian skala besar (Redis, session, cache, queue, database)';

    public function handle(): int
    {
        $this->info('Pemeriksaan kesiapan ujian — '.now()->toDateTimeString());
        $this->newLine();

        $items = (new SystemHealth)->run(function () {
            $token = SystemHealth::dispatchWorkerProbe();
            $deadline = microtime(true) + SystemHealth::WORKER_TIMEOUT_SECONDS;

            while (microtime(true) < $deadline) {
                if (SystemHealth::workerProbeDone($token)) {
                    return true;
                }

                usleep(500_000);
            }

            throw new RuntimeException(SystemHealth::workerFailureHint());
        });

        $section = null;

        foreach ($items as $item) {
            if ($item['section'] !== $section) {
                $section = $item['section'];
                $this->line("<comment>{$section}</comment>");
            }

            $hint = $item['hint'] !== '' ? " — {$item['hint']}" : '';

            match ($item['status']) {
                SystemHealth::PASS => $this->line("  <info>LULUS</info>      {$item['label']}"),
                SystemHealth::WARN => $this->line("  <comment>PERINGATAN</comment> {$item['label']}{$hint}"),
                SystemHealth::FAIL => $this->line("  <error>GAGAL</error>      {$item['label']}{$hint}"),
                default => $this->line("    {$item['label']}"),
            };
        }

        $this->newLine();
        $failures = SystemHealth::summarize($items)['failures'];

        if ($failures > 0) {
            $this->error("{$failures} pemeriksaan GAGAL. Perbaiki sebelum ujian.");

            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan wajib LULUS.');

        return self::SUCCESS;
    }
}
