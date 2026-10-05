<?php

namespace App\Livewire\Concerns;

use App\Enums\ExamAttemptStatus;
use App\Support\ExamDeadline;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Keeps an exam room tied to the deadline stored in the database.
 *
 * An admin can extend a running attempt from the livescore board, so the
 * deadline captured at mount goes stale. Every poll and every answer/navigation
 * action re-reads it and pushes the fresh value to the browser timer
 * (`exam-deadline-synced`). Once the deadline has really passed the attempt is
 * submitted and the room switches to the "Waktu Ujian Habis" screen instead of
 * letting the peserta keep working.
 *
 * Expects the component to declare `int $attemptExpiresAt`.
 */
trait EnforcesExamDeadline
{
    /** True once the attempt was closed because its time ran out. */
    #[Locked]
    public bool $timeUp = false;

    /** Where the time-up screen sends the peserta. */
    #[Locked]
    public ?string $resultUrl = null;

    /** The attempt row (at least `status` and `expires_at`), read fresh from the DB. */
    abstract protected function freshDeadlineAttempt(): ?Model;

    /** Submit the timed-out attempt and return the URL of its result page. */
    abstract protected function closeTimedOutAttempt(): string;

    /** Result page of an attempt that is already submitted. */
    abstract protected function submittedResultUrl(): string;

    /** Set by syncDeadline(): the attempt was closed while time still remained. */
    private bool $submittedBeforeDeadline = false;

    /**
     * Called by the background deadline poll and by the browser timer the
     * moment it reaches zero.
     *
     * While time remains nothing on screen changes, so the page is NOT
     * re-rendered: only the fresh deadline goes back (exam-deadline-synced).
     * With dozens of participants polling, re-sending the whole exam page
     * every few seconds was the main source of lag on answer clicks.
     */
    public function checkExpiry(): void
    {
        if ($this->timeUp) {
            // A poll that slips in before the time-up screen redirects must
            // not use up flash data meant for the result page.
            session()->reflash();
            $this->skipRender();

            return;
        }

        if (! $this->syncDeadline() || $this->deadlineRemainingSeconds() <= 0) {
            $this->leaveClosedAttempt();

            return;
        }

        $this->skipRender();
    }

    /**
     * Guard for actions that change answers or move between questions.
     * Returns false (and closes the attempt) once its time is over.
     */
    protected function ensureWithinDeadline(): bool
    {
        if ($this->timeUp) {
            return false;
        }

        if ($this->syncDeadline() && $this->withinAnswerGrace()) {
            return true;
        }

        $this->leaveClosedAttempt();

        return false;
    }

    /**
     * Re-read the deadline from the DB and send it to the browser timer.
     * Returns false when the attempt is no longer in progress.
     */
    protected function syncDeadline(): bool
    {
        $attempt = $this->freshDeadlineAttempt();

        if ($attempt === null || $attempt->status !== ExamAttemptStatus::InProgress) {
            $this->submittedBeforeDeadline = $attempt !== null && $attempt->expires_at->isFuture();

            return false;
        }

        $this->attemptExpiresAt = $attempt->expires_at->timestamp;
        $this->dispatch('exam-deadline-synced', remainingSeconds: $this->deadlineRemainingSeconds());

        return true;
    }

    protected function deadlineRemainingSeconds(): int
    {
        return max(0, $this->attemptExpiresAt - now()->timestamp);
    }

    protected function withinAnswerGrace(): bool
    {
        return ExamDeadline::acceptsAnswers(now()->setTimestamp($this->attemptExpiresAt));
    }

    /**
     * The attempt can't take answers any more. If it was already submitted
     * while time remained — typically "Selesai Ujian" went through but its
     * response was lost on a bad connection, and the peserta clicked again or
     * the poll noticed — go straight to the result instead of the misleading
     * "Waktu Ujian Habis" screen. Otherwise the time really ran out.
     */
    private function leaveClosedAttempt(): void
    {
        if ($this->submittedBeforeDeadline) {
            $this->redirect($this->submittedResultUrl(), navigate: false);

            return;
        }

        $this->closeForTimeUp();
    }

    private function closeForTimeUp(): void
    {
        // Set first so anything the close-out calls can't re-enter it.
        $this->timeUp = true;
        $this->resultUrl = $this->closeTimedOutAttempt();
    }
}
