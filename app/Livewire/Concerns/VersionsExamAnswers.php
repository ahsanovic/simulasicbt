<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;

/**
 * Answer versioning for exam rooms.
 *
 * Every Livewire request from an exam room carries X-Exam-Seq, a number the
 * browser only ever increases (exam-timer.js; kept in localStorage per
 * attempt so it keeps rising across refreshes). An answer row is written only
 * when the incoming number is newer than its stored answer_version, so an old
 * request that arrives late — e.g. a save cancelled after the timeout that
 * still reached the server after the participant saved a different pick —
 * is ignored instead of overwriting the newer answer.
 *
 * Requests without the header (tests, a proxy that strips it) keep the old
 * behaviour and simply save.
 */
trait VersionsExamAnswers
{
    /** Highest stored answer_version of the attempt, the browser counter starts above it. */
    #[Locked]
    public int $answerVersionBase = 0;

    /** URL of this exam room, used to reload it after a stale save. */
    abstract protected function examRoomUrl(): string;

    /** The request's X-Exam-Seq, or null for requests without one. */
    protected function incomingAnswerVersion(): ?int
    {
        $value = request()->header('X-Exam-Seq');

        return is_string($value) && ctype_digit($value) && (int) $value > 0 && (int) $value <= 4_000_000_000
            ? (int) $value
            : null;
    }

    /**
     * The save was refused because the stored answer is newer than this page
     * (the exam is open in another tab/device, or the admin reset it). Do not
     * carry on as if it was saved: reload the room so it shows exactly what
     * is stored. When this is an old timed-out request the browser already
     * gave up on it, so the reload never reaches anyone.
     */
    protected function reloadForStaleAnswer(string $table, int $answerId, int $incomingVersion): void
    {
        Log::warning('Stale exam answer ignored', [
            'table' => $table,
            'answer_id' => $answerId,
            'user_id' => auth()->id(),
            'incoming_version' => $incomingVersion,
        ]);

        session()->flash('error', 'Halaman dimuat ulang karena jawaban yang tersimpan lebih baru dari tampilan ini (ujian terbuka di tab/perangkat lain atau baru direset pengawas). Periksa kembali jawaban Anda.');
        $this->redirect($this->examRoomUrl(), navigate: false);
    }
}
