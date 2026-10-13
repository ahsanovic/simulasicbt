<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Livewire\Concerns\EnforcesExamDeadline;
use App\Services\ModeUjianRoom\DeviceToken;
use App\Services\ModeUjianRoom\RoomKind;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Services\ModeUjianRoom\RoomPayload;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mode Ujian exam room that runs in the browser (EXAM_CLIENT_ROOM): every
 * question is sent once, moving between questions happens in the browser and
 * answers go to ModeUjianRoomController in the background. This component only
 * opens the room and keeps the background deadline poll (time added by the
 * admin, closing an attempt whose browser went quiet).
 */
#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ruang Ujian')]
class ClientExamRoom extends Component
{
    use EnforcesExamDeadline {
        checkExpiry as private closeWhenExpired;
    }

    #[Locked]
    public string $kind = 'skd';

    #[Locked]
    public int $attemptId = 0;

    #[Locked]
    public int $attemptExpiresAt = 0;

    /** Sent with the first render only, never kept in the Livewire snapshot. */
    protected ?array $room = null;

    /** Who sits the exam (header, for the proctor); first render only, like $room. */
    protected ?string $participantName = null;

    protected ?string $participantNik = null;

    public function mount(string $kind): void
    {
        $roomKind = RoomKinds::get($kind);
        $attempt = $roomKind->activeAttempt((int) auth()->id());

        if ($attempt === null) {
            $this->redirect(route('peserta.mode-ujian.dashboard'), navigate: false);

            return;
        }

        if (! config('exam.client_room') || ($kind === 'skd' && $attempt->needsNameConfirmation())) {
            $this->redirect($roomKind->legacyRoomUrl($attempt), navigate: false);

            return;
        }

        if (! $attempt->isActive()) {
            // Time ran out while the participant was away: score the saved answers.
            $closed = $roomKind->closeExpired($attempt);
            session()->flash('error', 'Waktu ujian habis. Jawaban yang sudah tersimpan telah dikumpulkan.');
            $this->redirect($roomKind->resultUrl($closed), navigate: false);

            return;
        }

        $this->kind = $kind;
        $this->attemptId = (int) $attempt->getKey();
        $this->attemptExpiresAt = $attempt->expires_at->timestamp;
        $this->room = app(RoomPayload::class)->build($roomKind, $attempt, DeviceToken::issue($attempt));
        $this->participantName = $attempt->display_name ?: auth()->user()->name;
        $this->participantNik = auth()->user()->nik;
    }

    /**
     * Background deadline poll. Just past zero (within the answer grace) it
     * leaves closing to the browser's own time-up call, which brings the
     * answers not sent yet; it closes the attempt itself only after the grace,
     * when the browser has gone quiet.
     */
    public function checkExpiry(): void
    {
        if (! $this->timeUp && $this->syncDeadline() && $this->withinAnswerGrace()) {
            $this->skipRender();

            return;
        }

        $this->closeWhenExpired();
    }

    protected function freshDeadlineAttempt(): ?Model
    {
        return $this->roomKind()->ownedAttempt((int) auth()->id(), $this->attemptId);
    }

    protected function closeTimedOutAttempt(): string
    {
        $kind = $this->roomKind();

        return $kind->resultUrl($kind->submit($kind->ownedAttempt((int) auth()->id(), $this->attemptId)));
    }

    protected function submittedResultUrl(): string
    {
        $kind = $this->roomKind();

        return $kind->resultUrl($kind->ownedAttempt((int) auth()->id(), $this->attemptId));
    }

    private function roomKind(): RoomKind
    {
        return RoomKinds::get($this->kind);
    }

    public function render()
    {
        return view('livewire.peserta.mode-ujian.client-exam-room', [
            'room' => $this->room,
            'answersUrl' => route('peserta.mode-ujian.answers'),
            'finishUrl' => route('peserta.mode-ujian.finish'),
            'remaining' => $this->deadlineRemainingSeconds(),
            'participantName' => $this->participantName,
            'participantNik' => $this->participantNik,
        ]);
    }
}
