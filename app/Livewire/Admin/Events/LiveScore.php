<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventExamMode;
use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use App\Services\ExamService;
use App\Services\SkbExamService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Livescore Sesi')]
class LiveScore extends Component
{
    public int $eventId;

    public EventSession $session;

    /**
     * Which exam's attempts this board shows. Only meaningful — and only
     * shown as a picker — for a Both-mode event; SKD-only and SKB-only
     * events have exactly one possible value and never show the picker.
     */
    #[Url(as: 'jenis', except: 'skd')]
    public string $viewMode = 'skd';

    /**
     * Selected in-progress attempt ids (as strings for checkbox binding),
     * keyed by board ('skd'/'skb') and kept independent per board — so
     * ticking participants on the SKD board and switching to SKB never
     * loses that selection or bleeds into the SKB one, and vice versa.
     *
     * @var array{skd: list<string>, skb: list<string>}
     */
    public array $selected = ['skd' => [], 'skb' => []];

    /** @var array{skd: bool, skb: bool} */
    public array $selectAll = ['skd' => false, 'skb' => false];

    public int $addMinutes = 5;

    public bool $showAddTimeModal = false;

    /** Attempt id being extended, or null when extending everyone selected. */
    public ?int $addTimeTargetId = null;

    public string $search = '';

    public int $currentPage = 1;

    public int $perPage = 25;

    public function mount(Event $event, EventSession $session): void
    {
        abort_unless($session->event_id === $event->id, 404);

        $this->eventId = $event->id;
        $this->session = $session;

        if (! in_array($this->viewMode, ['skd', 'skb'], true)) {
            $this->viewMode = 'skd';
        }
    }

    /**
     * Resolved fresh each request (the board polls). Returns null once the event
     * is deleted while this screen stays open. Kept out of a hydrated model
     * property so Livewire doesn't 404 the poll trying to re-fetch a soft-deleted
     * event — which would freeze the deleted event's participants on screen.
     */
    #[Computed]
    public function event(): ?Event
    {
        return Event::query()
            ->whereKey($this->eventId)
            ->with('exam:id,title,duration_minutes')
            ->first();
    }

    /**
     * All of this event's sessions, for the session switcher.
     */
    #[Computed]
    public function eventSessions(): Collection
    {
        return $this->event?->sessions()->orderBy('name')->get(['id', 'name']) ?? collect();
    }

    private function examMode(): EventExamMode
    {
        return $this->event?->exam_mode ?? EventExamMode::Skd;
    }

    /**
     * A Both-mode event shows the exam-type picker; SKD-only and SKB-only
     * events don't (there is nothing to pick).
     */
    public function supportsBothBoards(): bool
    {
        return ($this->event?->is_mode_ujian ?? false) && $this->examMode() === EventExamMode::Both;
    }

    /**
     * Which board actually renders right now: SKD-only and SKB-only events
     * always show their one board; a Both event shows whichever the admin
     * picked via the exam-type dropdown (defaults to SKD).
     */
    private function activeBoard(): string
    {
        $event = $this->event;

        if ($event === null || ! $event->is_mode_ujian) {
            return 'skd';
        }

        return match ($event->exam_mode) {
            EventExamMode::Skb => 'skb',
            EventExamMode::Both => $this->viewMode,
            default => 'skd',
        };
    }

    /**
     * Participants whose time ran out while offline never submitted themselves,
     * so close them out before reporting status. Both attempt types are
     * finalized whenever they exist for this event — independent of which
     * board is currently selected — so a SKB attempt still gets closed out
     * while the admin happens to be looking at the SKD board, and vice versa.
     */
    private function closeExpiredAttempts(): void
    {
        $mode = $this->examMode();

        if ($mode->includesSkd()) {
            $expired = ExamAttempt::query()
                ->where('event_session_id', $this->session->id)
                ->expiredButOpen()
                ->get();

            if ($expired->isNotEmpty()) {
                app(ExamService::class)->finalizeExpiredAttempts($expired);
            }
        }

        if ($mode->includesSkb()) {
            $expiredSkb = SkbExamAttempt::query()
                ->where('event_session_id', $this->session->id)
                ->expiredButOpen()
                ->get();

            if ($expiredSkb->isNotEmpty()) {
                app(SkbExamService::class)->finalizeExpiredAttempts($expiredSkb);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function allRows(): array
    {
        $this->closeExpiredAttempts();

        return $this->activeBoard() === 'skb' ? $this->skbRows() : $this->skdRows();
    }

    /**
     * @return list<array{attempt_id: int, name: string, instansi: ?string, answered: int, total: int, score: int, status: ExamAttemptStatus, in_progress: bool, remaining: ?string, submitted_at: ?string}>
     */
    private function skdRows(): array
    {
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
                    'row_key' => 'skd-'.$attempt->id,
                    'attempt_id' => $attempt->id,
                    'name' => $attempt->resolvedDisplayName(),
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

    /**
     * SKB-only board: rows come from the session roster (not just attempts)
     * so a peserta who hasn't started yet still appears as "Belum Ujian"
     * instead of being missing from the board entirely.
     */
    private function skbRows(): array
    {
        $participants = $this->session->participants()->with('jabatanSkb:id,name')->get();

        $attempts = SkbExamAttempt::query()
            ->where('event_session_id', $this->session->id)
            ->with(['answers:id,skb_exam_attempt_id,selected_option_id'])
            ->get()
            ->keyBy('user_id');

        $liveScores = app(SkbExamService::class)->liveScores($attempts);

        return $participants
            ->map(fn (EventParticipant $participant) => $this->skbRowFor(
                $participant,
                $attempt = $attempts->get($participant->user_id),
                $attempt ? ($liveScores[$attempt->id] ?? null) : null,
            ))
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @param  array{benar: int, score: int}|null  $live
     */
    private function skbRowFor(EventParticipant $participant, ?SkbExamAttempt $attempt, ?array $live = null): array
    {
        $jabatan = $participant->jabatanSkb?->name ?? $participant->jabatan_label;

        if ($attempt === null) {
            return [
                'row_key' => 'skb-p'.$participant->id,
                'attempt_id' => null,
                'name' => $participant->name,
                'jabatan' => $jabatan,
                'answered' => 0,
                'total' => 0,
                'benar' => 0,
                'score' => 0,
                'status' => null,
                'status_label' => 'Belum Ujian',
                'in_progress' => false,
                'remaining' => null,
                'submitted_at' => null,
            ];
        }

        $total = $attempt->answers->count();
        $answered = $attempt->answers->whereNotNull('selected_option_id')->count();
        $inProgress = $attempt->status === ExamAttemptStatus::InProgress;

        return [
            'row_key' => 'skb-'.$attempt->id,
            'attempt_id' => $attempt->id,
            'name' => $participant->name,
            'jabatan' => $jabatan,
            'answered' => $answered,
            'total' => $total,
            'benar' => $live['benar'] ?? 0,
            'score' => $live['score'] ?? 0,
            'status' => $attempt->status,
            'status_label' => $inProgress ? 'Sedang Ujian' : 'Selesai',
            'in_progress' => $inProgress,
            'remaining' => $inProgress ? format_exam_remaining_time($attempt->remainingSeconds()) : null,
            'submitted_at' => $attempt->submitted_at?->format('H:i:s'),
        ];
    }

    #[Computed]
    public function filteredRows(): array
    {
        if (blank($this->search)) {
            return $this->allRows();
        }

        $search = strtolower(trim($this->search));

        return collect($this->allRows())
            ->filter(fn ($row) => str_contains(strtolower($row['name']), $search)
                || str_contains(strtolower($row['instansi'] ?? $row['jabatan'] ?? ''), $search))
            ->values()
            ->all();
    }

    #[Computed]
    public function rows(): array
    {
        $all = $this->filteredRows();
        $start = ($this->currentPage - 1) * $this->perPage;

        return array_slice($all, $start, $this->perPage);
    }

    #[Computed]
    public function totalPages(): int
    {
        return (int) ceil(count($this->filteredRows()) / $this->perPage);
    }

    #[Computed]
    public function summary(): array
    {
        $rows = collect($this->filteredRows());

        return [
            'total' => $rows->count(),
            'not_started' => $rows->whereNull('status')->count(),
            'in_progress' => $rows->where('status', ExamAttemptStatus::InProgress)->count(),
            'finished' => $rows->filter(fn ($row) => $row['status'] !== null && $row['status'] !== ExamAttemptStatus::InProgress)->count(),
        ];
    }

    /** @return list<string> Every attempt in this session — reset applies to finished ones too. */
    private function allAttemptIds(?string $board = null): array
    {
        $board ??= $this->activeBoard();
        $query = $board === 'skb' ? $this->session->skbAttempts() : $this->session->attempts();

        return $query
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->currentPage = 1;
        unset($this->filteredRows, $this->rows, $this->totalPages, $this->summary);
    }

    /**
     * Switching exam type just shows a different board's rows; selections
     * live per board in $selected/$selectAll (see their doc comments), so
     * they are deliberately left alone here instead of being wiped.
     */
    public function updatedViewMode(): void
    {
        $this->currentPage = 1;
        unset($this->allRows, $this->filteredRows, $this->rows, $this->totalPages, $this->summary);
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = max(1, min($page, $this->totalPages()));
    }

    /** selectAll as it was before this update, to tell which board's checkbox actually changed. */
    private array $selectAllBefore = [];

    public function updatingSelectAll(mixed $value, ?string $key): void
    {
        $this->selectAllBefore = $this->selectAll;
    }

    /**
     * Livewire sends either one board ("selectAll.skd" → bool, $key = 'skd')
     * or the whole property (array, $key = null). Only a board whose checkbox
     * actually flipped is re-selected/cleared, so picks on the other board stay.
     */
    public function updatedSelectAll(mixed $value, ?string $key): void
    {
        $changes = $key !== null ? [$key => $value] : (array) $value;
        $normalized = ['skd' => false, 'skb' => false];

        foreach ($normalized as $board => $_) {
            $before = (bool) ($this->selectAllBefore[$board] ?? false);
            $checked = array_key_exists($board, $changes) ? (bool) $changes[$board] : $before;
            $normalized[$board] = $checked;

            if ($checked !== $before) {
                $this->selected[$board] = $checked ? $this->allAttemptIds($board) : [];
            }
        }

        // Drop anything other than the two boards a crafted request might add.
        $this->selectAll = $normalized;
    }

    public function resetAttempt(int $attemptId, ExamService $examService, SkbExamService $skbExamService): void
    {
        if ($this->activeBoard() === 'skb') {
            $this->resetSkbAttempt($attemptId, $skbExamService);

            return;
        }

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
        session()->flash('success', "Ujian {$attempt->resolvedDisplayName()} direset — dimulai dari awal.");
    }

    private function resetSkbAttempt(int $attemptId, SkbExamService $skbExamService): void
    {
        $attempt = $this->session->skbAttempts()
            ->whereKey($attemptId)
            ->with('user:id,name')
            ->first();

        if ($attempt === null) {
            session()->flash('error', 'Peserta tidak ditemukan pada sesi ini.');

            return;
        }

        $skbExamService->resetAttempt($attempt);

        unset($this->rows, $this->summary);
        session()->flash('success', "Ujian SKB {$attempt->user?->name} direset — dimulai dari awal.");
    }

    public function resetSelected(ExamService $examService, SkbExamService $skbExamService): void
    {
        $board = $this->activeBoard();
        $ids = array_map('intval', $this->selected[$board]);

        if ($ids === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        if ($board === 'skb') {
            $attempts = $this->session->skbAttempts()->whereIn('id', $ids)->get();

            foreach ($attempts as $attempt) {
                $skbExamService->resetAttempt($attempt);
            }

            $this->selected[$board] = [];
            $this->selectAll[$board] = false;
            unset($this->rows, $this->summary);

            session()->flash('success', "Ujian SKB {$attempts->count()} peserta direset — dimulai dari awal.");

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

        $this->selected[$board] = [];
        $this->selectAll[$board] = false;
        unset($this->rows, $this->summary);

        session()->flash('success', "Ujian {$done} peserta direset — dimulai dari awal.");
    }

    private function examDurationMinutes(): int
    {
        if ($this->activeBoard() === 'skb') {
            return (int) ($this->event?->skb_duration_minutes ?? 0);
        }

        return (int) ($this->event?->exam?->duration_minutes ?? 0);
    }

    /**
     * A participant's remaining time may never exceed the exam duration, so the
     * headroom left for an extension is the duration minus what they still have.
     */
    private function maxAddableMinutes(ExamAttempt|SkbExamAttempt $attempt): int
    {
        $remaining = (int) ceil(max(0, $attempt->remainingSeconds()) / 60);

        return max(0, $this->examDurationMinutes() - $remaining);
    }

    /** @return Collection<int, ExamAttempt|SkbExamAttempt> */
    private function addTimeTargets()
    {
        $query = $this->activeBoard() === 'skb'
            ? $this->session->skbAttempts()->where('status', ExamAttemptStatus::InProgress)->with('user:id,name')
            : $this->session->attempts()->where('status', ExamAttemptStatus::InProgress)->with('user:id,name');

        if ($this->addTimeTargetId !== null) {
            $query->whereKey($this->addTimeTargetId);
        } else {
            $query->whereIn('id', array_map('intval', $this->selected[$this->activeBoard()]));
        }

        return $query->get();
    }

    private function targetDisplayName(ExamAttempt|SkbExamAttempt $attempt): string
    {
        return $attempt instanceof ExamAttempt ? $attempt->resolvedDisplayName() : ($attempt->user?->name ?? 'Peserta');
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
                ? ($attempts->first() !== null ? $this->targetDisplayName($attempts->first()) : 'Peserta')
                : $attempts->count().' peserta terpilih',
            'is_bulk' => $this->addTimeTargetId === null,
            // Shown in the modal so it's never ambiguous which exam this
            // extension applies to — always the board currently active.
            'board_label' => $this->activeBoard() === 'skb' ? 'SKB' : 'SKD',
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
        if ($this->selected[$this->activeBoard()] === []) {
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
        $isSkb = $this->activeBoard() === 'skb';

        $attempt = ($isSkb ? $this->session->skbAttempts() : $this->session->attempts())
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

        $message = "Waktu +{$minutes} menit untuk {$this->targetDisplayName($attempt)}.";

        if ($minutes < $requested) {
            $message .= ' Dipotong agar sisa waktu tidak melebihi durasi ujian.';
        }

        session()->flash('success', $message);
    }

    public function addTimeToSelected(): void
    {
        $board = $this->activeBoard();
        $requested = $this->normalizedMinutes();
        $ids = array_map('intval', $this->selected[$board]);

        if ($ids === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        $isSkb = $board === 'skb';

        $attempts = ($isSkb ? $this->session->skbAttempts() : $this->session->attempts())
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

        $this->selected[$board] = [];
        $this->selectAll[$board] = false;
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

    private function extendAttempt(ExamAttempt|SkbExamAttempt $attempt, int $minutes): void
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

    /**
     * Called by wire:poll. Leaves the screen if the event was deleted while it
     * stayed open, so a deleted event's board stops showing participants.
     */
    public function pollBoard(): void
    {
        if ($this->event === null) {
            $this->redirect(route('admin.events.index'), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.admin.events.live-score', [
            'event' => $this->event,
            'category' => $this->activeBoard(),
            'showExamTypePicker' => $this->supportsBothBoards(),
        ]);
    }
}
