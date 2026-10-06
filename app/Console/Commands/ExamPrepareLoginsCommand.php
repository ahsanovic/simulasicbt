<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use App\Support\ModeUjianPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Run before an exam day: re-hashes the event participants' passwords with
 * the cheaper Mode Ujian cost, so their first login on the day is already
 * light. Only accounts whose password is still their NIK (the Mode Ujian
 * default) can be converted here; any other account is converted on its
 * next exam login instead. A normal (non-exam) login restores the default
 * cost, so run this again before each exam day.
 */
class ExamPrepareLoginsCommand extends Command
{
    protected $signature = 'exam:prepare-logins {event : ID event Mode Ujian}';

    protected $description = 'Ringankan login peserta Mode Ujian sebelum hari ujian (hash password ke biaya Mode Ujian)';

    public function handle(): int
    {
        $event = Event::query()->find($this->argument('event'));

        if ($event === null) {
            $this->error('Event tidak ditemukan.');

            return self::FAILURE;
        }

        $users = User::query()
            ->where('role', UserRole::Peserta)
            ->whereNotNull('nik')
            ->whereIn('id', $event->participants()->select('user_id'))
            ->get(['id', 'nik', 'password']);

        $converted = $ready = $skipped = 0;
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            if (! ModeUjianPassword::needsRehash($user->password)) {
                $ready++;
            } elseif (Hash::check($user->nik, $user->password)) {
                $user->forceFill(['password' => ModeUjianPassword::hash($user->nik)])->saveQuietly();
                $converted++;
            } else {
                $skipped++; // password changed by the participant: converted on its next exam login
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Peserta event \"{$event->name}\": {$converted} diringankan, {$ready} sudah ringan, {$skipped} dilewati (password bukan NIK).");

        return self::SUCCESS;
    }
}
