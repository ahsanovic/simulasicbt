<?php

namespace App\Console\Commands;

use App\Support\ExamQuestionCache;
use Illuminate\Console\Command;

/**
 * Run 10–30 minutes before a large exam: loads the content of every active
 * question into the cache in batches, so the exam rooms do not all fetch the
 * same questions from MySQL when hundreds of participants press "Mulai".
 * Safe to run any time; it only reads the bank.
 */
class ExamWarmCommand extends Command
{
    protected $signature = 'exam:warm';

    protected $description = 'Panaskan cache isi soal SKD/SKB sebelum ujian (jalankan 10–30 menit sebelum mulai)';

    public function handle(): int
    {
        $started = microtime(true);
        $counts = ExamQuestionCache::warm();

        $this->info(sprintf(
            'Cache soal siap: %d soal SKD, %d soal SKB (%.1f detik). Berlaku %d jam.',
            $counts['skd'],
            $counts['skb'],
            microtime(true) - $started,
            ExamQuestionCache::WARM_TTL_SECONDS / 3600,
        ));

        return self::SUCCESS;
    }
}
