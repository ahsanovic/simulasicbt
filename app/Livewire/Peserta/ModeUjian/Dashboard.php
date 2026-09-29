<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\EventParticipant;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use App\Services\SkbExamService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Mode Ujian')]
class Dashboard extends Component
{
    public EventParticipant $participant;

    public bool $showPinModal = false;

    public string $pinPhase = 'skd';

    public string $pinInput = '';

    public ?string $pinError = null;

    public function mount(): void
    {
        $participant = EventParticipant::query()
            ->where('user_id', Auth::id())
            ->whereHas('event', fn ($q) => $q->where('is_mode_ujian', true)->where('status', EventStatus::Active))
            ->with(['event.exam', 'eventSession'])
            ->latest()
            ->first();

        if ($participant === null) {
            abort(403, 'Tidak ada event Mode Ujian aktif untuk akun ini.');
        }

        $this->participant = $participant;
    }

    public function openPinModal(string $phase): void
    {
        $this->pinPhase = $phase;
        $this->pinInput = '';
        $this->pinError = null;
        $this->showPinModal = true;
    }

    public function closePinModal(): void
    {
        $this->showPinModal = false;
    }

    public function submitPin(ExamService $examService, SkbExamService $skbExamService): void
    {
        $event = $this->participant->event;
        $session = $this->participant->eventSession;

        if ($session === null) {
            $this->pinError = 'Sesi belum diset oleh admin untuk akun Anda. Hubungi admin.';

            return;
        }

        $expectedPin = $this->pinPhase === 'skd' ? $session->skd_pin : $session->skb_pin;

        if ($expectedPin === null || trim($this->pinInput) !== (string) $expectedPin) {
            $this->pinError = 'PIN sesi salah.';

            return;
        }

        if ($this->pinPhase === 'skd') {
            $attempt = ExamAttempt::query()
                ->where('event_id', $event->id)
                ->where('user_id', Auth::id())
                ->where('status', ExamAttemptStatus::InProgress)
                ->latest('id')
                ->first();

            if ($attempt === null) {
                $attempt = $examService->startAttempt($event->exam, Auth::user(), $event->id, $this->participant->event_session_id);
                $attempt->update(['display_name' => $this->participant->name]);
            }

            $this->redirect(route('peserta.exam.room', $attempt->exam_id), navigate: false);

            return;
        }

        $attempt = $skbExamService->findActiveAttempt($event, Auth::id());

        if ($attempt === null) {
            $attempt = $skbExamService->startAttempt($event, $this->participant);
        }

        $this->redirect(route('peserta.mode-ujian.skb.room'), navigate: false);
    }

    public function render()
    {
        $event = $this->participant->event;

        $skdAttempt = $event->exam_mode->includesSkd()
            ? ExamAttempt::query()
                ->where('event_id', $event->id)
                ->where('user_id', Auth::id())
                ->latest('id')
                ->first()
            : null;

        $skbExamService = app(SkbExamService::class);
        $skbAttempt = $event->exam_mode->includesSkb()
            ? $skbExamService->findLatestAttempt($event, Auth::id())
            : null;

        $skdDone = $skdAttempt?->status === ExamAttemptStatus::Submitted || $skdAttempt?->status === ExamAttemptStatus::Expired;
        $skbDone = $skbAttempt?->status === ExamAttemptStatus::Submitted || $skbAttempt?->status === ExamAttemptStatus::Expired;

        $skdInProgress = $skdAttempt?->status === ExamAttemptStatus::InProgress;
        $skbInProgress = $skbAttempt?->status === ExamAttemptStatus::InProgress;

        // SKB unlocks only after SKD is done when both are required; SKB-only events unlock immediately.
        $skbUnlocked = $event->exam_mode->value === 'skb' || $skdDone;

        return view('livewire.peserta.mode-ujian.dashboard', [
            'event' => $event,
            'skdAttempt' => $skdAttempt,
            'skbAttempt' => $skbAttempt,
            'skdDone' => $skdDone,
            'skbDone' => $skbDone,
            'skbUnlocked' => $skbUnlocked,
            'skdRemainingSeconds' => $skdInProgress ? $skdAttempt->remainingSeconds() : null,
            'skbRemainingSeconds' => $skbInProgress ? $skbAttempt->remainingSeconds() : null,
        ]);
    }
}
