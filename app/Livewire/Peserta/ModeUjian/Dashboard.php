<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use App\Services\ExamService;
use App\Services\SkbExamService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    /**
     * Resume or start SKD/SKB after the session PIN. All rules live here, on
     * the server: the dashboard buttons are only cosmetics, a crafted call to
     * this method must hit the same checks.
     */
    public function submitPin(ExamService $examService, SkbExamService $skbExamService): void
    {
        $event = $this->participant->event;
        $session = $this->participant->eventSession;
        $phase = in_array($this->pinPhase, ['skd', 'skb'], true) ? $this->pinPhase : null;

        if ($phase === null
            || ($phase === 'skd' && ! $event->exam_mode->includesSkd())
            || ($phase === 'skb' && ! $event->exam_mode->includesSkb())) {
            $this->pinError = 'Tahap ujian ini tidak tersedia pada event Anda.';

            return;
        }

        if ($session === null) {
            $this->pinError = 'Sesi belum diset oleh admin untuk akun Anda. Hubungi admin.';

            return;
        }

        $expectedPin = $phase === 'skd' ? $session->skd_pin : $session->skb_pin;

        if ($expectedPin === null || trim($this->pinInput) !== (string) $expectedPin) {
            $this->pinError = 'PIN sesi salah.';

            return;
        }

        try {
            // Lock this participant's row so a double click / second tab
            // can't start two attempts at once: the second request waits,
            // then finds the first attempt and resumes it.
            DB::transaction(function () use ($phase, $event, $session, $examService, $skbExamService) {
                EventParticipant::query()->whereKey($this->participant->id)->lockForUpdate()->first();

                $phase === 'skd'
                    ? $this->resumeOrStartSkd($event, $session, $examService)
                    : $this->resumeOrStartSkb($event, $session, $skbExamService);
            });
        } catch (ValidationException $exception) {
            // e.g. finished already, session not open, question bank too small.
            $this->pinError = collect($exception->errors())->flatten()->first()
                ?? 'Ujian tidak dapat dimulai. Hubungi pengawas.';

            return;
        }

        $this->redirect(
            $phase === 'skd' ? route('peserta.exam.room', $event->exam_id) : route('peserta.mode-ujian.skb.room'),
            navigate: false,
        );
    }

    private function resumeOrStartSkd(Event $event, EventSession $session, ExamService $examService): void
    {
        $attempts = ExamAttempt::query()
            ->where('event_id', $event->id)
            ->where('user_id', Auth::id());

        if ((clone $attempts)->where('status', ExamAttemptStatus::InProgress)->exists()) {
            return; // resume — allowed even if the session was closed meanwhile
        }

        if ((clone $attempts)->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])->exists()) {
            throw ValidationException::withMessages(['pin' => 'Anda sudah menyelesaikan ujian SKD. Ujian tidak dapat diulang.']);
        }

        $this->ensureSessionIsOpen($session);

        $attempt = $examService->startAttempt($event->exam, Auth::user(), $event->id, $this->participant->event_session_id);
        $attempt->update(['display_name' => $this->participant->name]);
    }

    private function resumeOrStartSkb(Event $event, EventSession $session, SkbExamService $skbExamService): void
    {
        if ($skbExamService->findActiveAttempt($event, Auth::id()) !== null) {
            return; // resume — allowed even if the session was closed meanwhile
        }

        $finished = SkbExamAttempt::query()
            ->where('event_id', $event->id)
            ->where('user_id', Auth::id())
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->exists();

        if ($finished) {
            throw ValidationException::withMessages(['pin' => 'Anda sudah menyelesaikan ujian SKB. Ujian tidak dapat diulang.']);
        }

        $this->ensureSessionIsOpen($session);

        $skbExamService->startAttempt($event, $this->participant);
    }

    /** New attempts only start while the proctor has the session open (status Aktif). */
    private function ensureSessionIsOpen(EventSession $session): void
    {
        $status = $session->fresh()?->status;

        if ($status === EventStatus::Closed) {
            throw ValidationException::withMessages(['pin' => 'Sesi Anda sudah ditutup. Hubungi pengawas.']);
        }

        if ($status !== EventStatus::Active) {
            throw ValidationException::withMessages(['pin' => 'Sesi Anda belum dibuka oleh pengawas. Tunggu instruksi pengawas.']);
        }
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
