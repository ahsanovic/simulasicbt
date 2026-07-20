<?php

namespace App\Livewire\Peserta\Events;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'events', 'showNav' => true])]
#[Title('Event Offline')]
class Index extends Component
{
    public ?int $selectedSessionId = null;

    public string $code = '';

    public function openJoinModal(int $sessionId): void
    {
        $this->selectedSessionId = $sessionId;
        $this->code = '';
        $this->resetValidation();
    }

    public function closeJoinModal(): void
    {
        $this->selectedSessionId = null;
        $this->code = '';
        $this->resetValidation();
    }

    public function join(): void
    {
        $session = EventSession::query()
            ->with('event.exam')
            ->findOrFail($this->selectedSessionId);

        $event = $session->event;

        if ($event === null || $event->status !== EventStatus::Active || ! $session->isJoinable() || $event->exam === null) {
            $this->addError('code', 'Sesi tidak tersedia untuk diikuti saat ini.');

            return;
        }

        if (strtoupper(trim($this->code)) !== strtoupper((string) $session->code)) {
            $this->addError('code', 'Kode sesi salah.');

            return;
        }

        $user = auth()->user();

        $finished = ExamAttempt::query()
            ->where('event_session_id', $session->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->exists();

        if ($finished) {
            $this->addError('code', 'Anda sudah menyelesaikan sesi ini. Hubungi panitia bila perlu mengulang — ujian Anda dapat direset dari panel panitia.');

            return;
        }

        $sessionAttempt = ExamAttempt::query()
            ->where('event_session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($sessionAttempt && $sessionAttempt->isActive()) {
            $this->redirect(route('peserta.exam.room', $event->exam));

            return;
        }

        // Block if the user has another genuinely active attempt for this exam package.
        $otherActive = ExamAttempt::query()
            ->where('exam_id', $event->exam_id)
            ->where('user_id', $user->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->get()
            ->first(fn (ExamAttempt $attempt) => $attempt->isActive());

        if ($otherActive) {
            $this->addError('code', 'Anda masih memiliki ujian berjalan untuk paket ini. Selesaikan terlebih dahulu.');

            return;
        }

        // Clean up any stale (expired-but-open) attempts for this exam to avoid ambiguity in the exam room.
        ExamAttempt::query()
            ->where('exam_id', $event->exam_id)
            ->where('user_id', $user->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->update(['status' => ExamAttemptStatus::Expired]);

        app(ExamService::class)->startAttempt($event->exam, $user, $event->id, $session->id);

        $this->redirect(route('peserta.exam.room', $event->exam));
    }

    /**
     * Resume a session exam already in progress — no code required, since the
     * participant already joined with the code before the interruption.
     */
    public function resume(int $sessionId): void
    {
        $attempt = ExamAttempt::query()
            ->where('event_session_id', $sessionId)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->with('event.exam')
            ->latest('id')
            ->first();

        if ($attempt && $attempt->isActive() && $attempt->event?->exam) {
            $this->redirect(route('peserta.exam.room', $attempt->event->exam));

            return;
        }

        session()->flash('error', 'Ujian tidak lagi berlangsung atau waktu sudah habis.');
    }

    public function render()
    {
        $events = Event::query()
            ->where('status', EventStatus::Active)
            ->with([
                'exam:id,title,duration_minutes',
                'sessions' => fn ($query) => $query->where('status', EventStatus::Active)->orderBy('starts_at')->orderBy('name'),
            ])
            ->get()
            ->map(function (Event $event) {
                $event->setRelation('sessions', $event->sessions->filter(fn (EventSession $session) => $session->isJoinable())->values());

                return $event;
            })
            ->filter(fn (Event $event) => $event->exam !== null && $event->sessions->isNotEmpty())
            ->values();

        // Any session exam still in progress for this participant (e.g. after a
        // disconnect) so they can resume in one click without re-entering the code.
        $activeAttempt = ExamAttempt::query()
            ->whereNotNull('event_session_id')
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->with(['event.exam', 'eventSession:id,name'])
            ->latest('id')
            ->get()
            ->first(fn (ExamAttempt $attempt) => $attempt->isActive() && $attempt->event?->exam);

        return view('livewire.peserta.events.index', compact('events', 'activeAttempt'));
    }
}
