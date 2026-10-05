<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Accounts for the exam load test (tools/loadtest/exam-load.mjs).
 *
 * Creates N participants (loadtest_001, loadtest_002, …) registered in one
 * event session, and removes them again — with their attempts and answers —
 * with --cleanup. Only ever touches usernames starting with "loadtest_".
 */
class ExamLoadTestUsersCommand extends Command
{
    private const PREFIX = 'loadtest_';

    protected $signature = 'exam:loadtest-users
        {session? : ID sesi event tujuan (wajib kecuali --cleanup)}
        {--count=420 : Jumlah akun peserta uji}
        {--password=loadtest123 : Password semua akun uji}
        {--jabatan= : ID jabatan SKB (untuk uji SKB)}
        {--cleanup : Hapus semua akun loadtest_ beserta attempt dan jawabannya}';

    protected $description = 'Buat / hapus akun peserta untuk uji beban mode ujian';

    public function handle(): int
    {
        return $this->option('cleanup') ? $this->cleanup() : $this->seed();
    }

    private function seed(): int
    {
        $session = EventSession::query()->with('event')->find($this->argument('session'));

        if ($session === null) {
            $this->error('Sesi tidak ditemukan. Contoh: php artisan exam:loadtest-users 12 --count=420');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $password = Hash::make((string) $this->option('password'));
        $jabatanId = $this->option('jabatan') !== null ? (int) $this->option('jabatan') : null;

        $this->info("Membuat {$count} akun uji di event \"{$session->event->name}\", sesi \"{$session->name}\"...");
        $bar = $this->output->createProgressBar($count);

        for ($i = 1; $i <= $count; $i++) {
            $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $user = User::query()->updateOrCreate(
                ['username' => self::PREFIX.$number],
                [
                    'name' => 'Uji Beban '.$number,
                    'email' => self::PREFIX.$number.'@loadtest.invalid',
                    'password' => $password,
                    'role' => UserRole::Peserta,
                    'is_active' => true,
                ],
            );

            EventParticipant::query()->updateOrCreate(
                ['event_id' => $session->event_id, 'user_id' => $user->id],
                [
                    'event_session_id' => $session->id,
                    'name' => 'Uji Beban '.$number,
                    'nik' => '9900000000'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'jabatan_label' => 'Uji Beban',
                    'jabatan_skb_id' => $jabatanId,
                ],
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Selesai. Login: '.self::PREFIX.'001 … '.self::PREFIX.str_pad((string) $count, 3, '0', STR_PAD_LEFT));

        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        // In SQL LIKE "_" matches any character ("loadtest_%" also matches
        // "loadtester1"), so the exact prefix is checked in PHP: real
        // accounts are never deleted.
        $users = User::query()
            ->where('username', 'like', 'loadtest%')
            ->get(['id', 'username'])
            ->filter(fn (User $user) => str_starts_with($user->username, self::PREFIX))
            ->pluck('id');

        if ($users->isEmpty()) {
            $this->info('Tidak ada akun loadtest_.');

            return self::SUCCESS;
        }

        foreach ($users->chunk(100) as $ids) {
            DB::transaction(function () use ($ids) {
                $attempts = ExamAttempt::query()->whereIn('user_id', $ids)->pluck('id');
                DB::table('exam_answers')->whereIn('exam_attempt_id', $attempts)->delete();
                ExamAttempt::query()->whereIn('id', $attempts)->delete();

                $skbAttempts = SkbExamAttempt::query()->whereIn('user_id', $ids)->pluck('id');
                DB::table('skb_exam_answers')->whereIn('skb_exam_attempt_id', $skbAttempts)->delete();
                SkbExamAttempt::query()->whereIn('id', $skbAttempts)->delete();

                EventParticipant::query()->whereIn('user_id', $ids)->delete();
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
                User::query()->whereIn('id', $ids)->delete();
            });
        }

        $this->info("Dihapus: {$users->count()} akun loadtest_ beserta attempt dan jawabannya.");

        return self::SUCCESS;
    }
}
