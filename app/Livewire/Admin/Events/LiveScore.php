<?php

namespace App\Livewire\Admin\Events;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Livescore Sesi')]
class LiveScore extends Component
{
    public Event $event;

    public EventSession $session;

    /** @var list<string> Selected in-progress attempt ids (as strings for checkbox binding). */
    public array $selected = [];

    public bool $selectAll = false;

    public int $addMinutes = 5;

    public bool $showAddTimeModal = false;

    /** Attempt id being extended, or null when extending everyone selected. */
    public ?int $addTimeTargetId = null;

    public function mount(Event $event, EventSession $session): void
    {
        abort_unless($session->event_id === $event->id, 404);

        $this->event = $event->load('exam:id,title,duration_minutes');
        $this->session = $session;
    }

    /**
     * @return list<array{attempt_id: int, name: string, instansi: ?string, answered: int, total: int, score: int, status: ExamAttemptStatus, in_progress: bool, remaining: ?string, submitted_at: ?string}>
     */
    /**
     * Participants whose time ran out while offline never submitted themselves,
     * so close them out before reporting status.
     */
    private function closeExpiredAttempts(): void
    {
        $expired = ExamAttempt::query()
            ->where('event_session_id', $this->session->id)
            ->expiredButOpen()
            ->get();

        if ($expired->isNotEmpty()) {
            app(ExamService::class)->finalizeExpiredAttempts($expired);
        }
    }

    #[Computed]
    public function rows(): array
    {
        $this->closeExpiredAttempts();

        $attempts = ExamAttempt::query()
            ->where('event_session_id', $this->session->id)
            ->with([
                'user:id,name,instansi_id',
                'user.instansi:id,nama',
                'answers:id,exam_attempt_id,question_id,selected_option_id',
                'answers.selectedOption:id,question_id,score_weight,is_correct',
                'answers.question:id,subject_id',
                'answers.question.subject:id,code',
            ])
            ->get();

        return $attempts
            ->map(function (ExamAttempt $attempt) {
                $total = $attempt->answers->count();
                $answered = $attempt->answers
                    ->filter(fn ($answer) => $answer->selected_option_id !== null)
                    ->count();

                $inProgress = $attempt->status === ExamAttemptStatus::InProgress;

                if ($inProgress) {
                    $scores = $attempt->calculateScores();
                } else {
                    $scores = [
                        'twk' => (int) $attempt->score_twk,
                        'tiu' => (int) $attempt->score_tiu,
                        'tkp' => (int) $attempt->score_tkp,
                        'total' => (int) $attempt->total_score,
                    ];
                }

                return [
                    'attempt_id' => $attempt->id,
                    'name' => $attempt->user?->name ?? 'Peserta',
                    'instansi' => $attempt->user?->instansi?->nama,
                    'answered' => $answered,
                    'total' => $total,
                    'twk' => $scores['twk'],
                    'tiu' => $scores['tiu'],
                    'tkp' => $scores['tkp'],
                    'score' => $scores['total'],
                    'status' => $attempt->status,
                    'in_progress' => $inProgress,
                    'remaining' => $inProgress ? format_exam_remaining_time($attempt->remainingSeconds()) : null,
                    'submitted_at' => $attempt->submitted_at?->format('H:i:s'),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    #[Computed]
    public function summary(): array
    {
        $rows = $this->rows();

        return [
            'total' => count($rows),
            'in_progress' => collect($rows)->where('status', ExamAttemptStatus::InProgress)->count(),
            'finished' => collect($rows)->where('status', '!=', ExamAttemptStatus::InProgress)->count(),
        ];
    }

    /** @return list<string> Every attempt in this session — reset applies to finished ones too. */
    private function allAttemptIds(): array
    {
        return $this->session->attempts()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->allAttemptIds() : [];
    }

    public function resetAttempt(int $attemptId, ExamService $examService): void
    {
        $attempt = $this->session->attempts()
            ->whereKey($attemptId)
            ->with('user:id,name')
            ->first();

        if ($attempt === null) {
            session()->flash('error', 'Peserta tidak ditemukan pada sesi ini.');

            return;
        }

        try {
            $examService->resetAttempt($attempt);
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first() ?? 'Gagal mengulang ujian.');

            return;
        }

        unset($this->rows, $this->summary);
        session()->flash('success', "Ujian {$attempt->user?->name} direset — dimulai dari awal.");
    }

    public function resetSelected(ExamService $examService): void
    {
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        $attempts = $this->session->attempts()->whereIn('id', $ids)->get();
        $done = 0;

        foreach ($attempts as $attempt) {
            try {
                $examService->resetAttempt($attempt);
                $done++;
            } catch (ValidationException $exception) {
                session()->flash('error', collect($exception->errors())->flatten()->first() ?? 'Gagal mengulang ujian.');

                return;
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        unset($this->rows, $this->summary);

        session()->flash('success', "Ujian {$done} peserta direset — dimulai dari awal.");
    }

    private function examDurationMinutes(): int
    {
        return (int) ($this->event->exam?->duration_minutes ?? 0);
    }

    /**
     * A participant's remaining time may never exceed the exam duration, so the
     * headroom left for an extension is the duration minus what they still have.
     */
    private function maxAddableMinutes(ExamAttempt $attempt): int
    {
        $remaining = (int) ceil(max(0, $attempt->remainingSeconds()) / 60);

        return max(0, $this->examDurationMinutes() - $remaining);
    }

    /** @return Collection<int, ExamAttempt> */
    private function addTimeTargets()
    {
        $query = $this->session->attempts()
            ->where('status', ExamAttemptStatus::InProgress)
            ->with('user:id,name');

        if ($this->addTimeTargetId !== null) {
            $query->whereKey($this->addTimeTargetId);
        } else {
            $query->whereIn('id', array_map('intval', $this->selected));
        }

        return $query->get();
    }

    /**
     * Details shown in the add-time popup: who is affected and the ceiling.
     */
    #[Computed]
    public function addTimeContext(): array
    {
        $attempts = $this->addTimeTargets();
        $max = null;

        foreach ($attempts as $attempt) {
            $headroom = $this->maxAddableMinutes($attempt);
            $max = $max === null ? $headroom : min($max, $headroom);
        }

        return [
            'count' => $attempts->count(),
            'label' => $this->addTimeTargetId !== null
                ? ($attempts->first()?->user?->name ?? 'Peserta')
                : $attempts->count().' peserta terpilih',
            'is_bulk' => $this->addTimeTargetId === null,
            'duration' => $this->examDurationMinutes(),
            'remaining' => $attempts->count() === 1
                ? (int) ceil(max(0, $attempts->first()->remainingSeconds()) / 60)
                : null,
            'max' => $max ?? 0,
        ];
    }

    public function openAddTime(int $attemptId): void
    {
        $this->addTimeTargetId = $attemptId;
        $this->showAddTimeModal = true;
        unset($this->addTimeContext);
        $this->clampAddMinutes();
    }

    public function openAddTimeForSelected(): void
    {
        if ($this->selected === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        $this->addTimeTargetId = null;
        $this->showAddTimeModal = true;
        unset($this->addTimeContext);
        $this->clampAddMinutes();
    }

    public function closeAddTimeModal(): void
    {
        $this->showAddTimeModal = false;
        $this->addTimeTargetId = null;
        unset($this->addTimeContext);
    }

    public function updatedAddMinutes(): void
    {
        if ($this->showAddTimeModal) {
            $this->clampAddMinutes();
        }
    }

    private function clampAddMinutes(): void
    {
        $max = $this->addTimeContext()['max'];
        $this->addMinutes = $max > 0
            ? max(1, min((int) $this->addMinutes ?: 1, $max))
            : 0;
    }

    public function confirmAddTime(): void
    {
        if ($this->addTimeTargetId !== null) {
            $this->addTime($this->addTimeTargetId);
        } else {
            $this->addTimeToSelected();
        }

        $this->closeAddTimeModal();
    }

    public function addTime(int $attemptId): void
    {
        $attempt = $this->session->attempts()
            ->whereKey($attemptId)
            ->where('status', ExamAttemptStatus::InProgress)
            ->with('user:id,name')
            ->first();

        if ($attempt === null) {
            session()->flash('error', 'Peserta tidak sedang mengerjakan ujian.');

            return;
        }

        $requested = $this->normalizedMinutes();
        $headroom = $this->maxAddableMinutes($attempt);

        if ($headroom <= 0) {
            session()->flash('error', 'Sisa waktu sudah mencapai durasi ujian — tidak bisa ditambah lagi.');

            return;
        }

        $minutes = min($requested, $headroom);
        $this->extendAttempt($attempt, $minutes);
        unset($this->rows, $this->summary);

        $message = "Waktu +{$minutes} menit untuk {$attempt->user?->name}.";

        if ($minutes < $requested) {
            $message .= ' Dipotong agar sisa waktu tidak melebihi durasi ujian.';
        }

        session()->flash('success', $message);
    }

    public function addTimeToSelected(): void
    {
        $requested = $this->normalizedMinutes();
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        $attempts = $this->session->attempts()
            ->whereIn('id', $ids)
            ->where('status', ExamAttemptStatus::InProgress)
            ->get();

        $applied = 0;
        $capped = 0;
        $skipped = 0;

        foreach ($attempts as $attempt) {
            $headroom = $this->maxAddableMinutes($attempt);

            if ($headroom <= 0) {
                $skipped++;

                continue;
            }

            $minutes = min($requested, $headroom);

            if ($minutes < $requested) {
                $capped++;
            }

            $this->extendAttempt($attempt, $minutes);
            $applied++;
        }

        $this->selected = [];
        $this->selectAll = false;
        unset($this->rows, $this->summary);

        if ($applied === 0) {
            session()->flash('error', 'Sisa waktu semua peserta terpilih sudah mencapai durasi ujian.');

            return;
        }

        $message = "Waktu ditambahkan untuk {$applied} peserta.";

        if ($capped > 0) {
            $message .= " {$capped} peserta dipotong agar tidak melebihi durasi ujian.";
        }

        if ($skipped > 0) {
            $message .= " {$skipped} peserta dilewati (sudah mencapai batas).";
        }

        session()->flash('success', $message);
    }

    private function extendAttempt(ExamAttempt $attempt, int $minutes): void
    {
        // Extend from whichever is later — now or the current deadline — so a
        // just-expired attempt (e.g. after a disconnect) is revived, not left in the past.
        $base = $attempt->expires_at->isPast() ? now() : $attempt->expires_at;

        $attempt->update([
            'expires_at' => $base->addMinutes($minutes),
            'status' => ExamAttemptStatus::InProgress,
        ]);
    }

    private function normalizedMinutes(): int
    {
        return max(1, min(180, (int) $this->addMinutes));
    }

    public function render()
    {
        return view('livewire.admin.events.live-score');
    }
}
