<?php

namespace App\Livewire\Public;

use App\Enums\EventExamMode;
use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use App\Services\ExamService;
use App\Services\SkbExamService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Livescore')]
class LiveScoreShow extends Component
{
    public int $eventId;

    public ?int $sessionId = null;

    /**
     * Which exam's leaderboard this shows. Only meaningful — and only shown
     * as a picker — for a Both-mode event; SKD-only and SKB-only events have
     * exactly one possible value and never show the picker.
     */
    #[Url(as: 'jenis', except: 'skd')]
    public string $viewMode = 'skd';

    public function mount(Event $event): void
    {
        abort_unless($event->public_livescore, 404);

        $this->eventId = $event->id;

        if (! in_array($this->viewMode, ['skd', 'skb'], true)) {
            $this->viewMode = 'skd';
        }
    }

    /**
     * Resolved fresh on every request (the board is polled and venue screens
     * stay open for hours). Returns null once the event is deleted or its public
     * livescore is switched off. The event is intentionally NOT held as a
     * hydrated Livewire model property: Livewire re-fetches model properties by
     * key each request, and a soft-deleted model resolves to null and 404s the
     * poll — freezing the stale board (with participant names) on screen.
     */
    #[Computed]
    public function event(): ?Event
    {
        return Event::query()
            ->whereKey($this->eventId)
            ->where('public_livescore', true)
            ->with('exam:id,title,duration_minutes')
            ->first();
    }

    #[Computed]
    public function sessions()
    {
        return $this->event
            ? $this->event->sessions()->orderBy('name')->get(['id', 'name'])
            : collect();
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
     * always show their one board; a Both event shows whichever the venue
     * screen operator picked via the exam-type dropdown (defaults to SKD).
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
     * board is currently selected.
     */
    private function closeExpiredAttempts(): void
    {
        $mode = $this->examMode();

        if ($mode->includesSkd()) {
            $expired = ExamAttempt::query()
                ->where('event_id', $this->eventId)
                ->expiredButOpen()
                ->get();

            if ($expired->isNotEmpty()) {
                app(ExamService::class)->finalizeExpiredAttempts($expired);
            }
        }

        if ($mode->includesSkb()) {
            $expiredSkb = SkbExamAttempt::query()
                ->where('event_id', $this->eventId)
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
    public function rows(): array
    {
        $this->closeExpiredAttempts();

        $rows = $this->activeBoard() === 'skb' ? $this->skbRows() : $this->skdRows();

        return collect($rows)
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();
    }

    /**
     * @return list<array{name: string, instansi: ?string, session: ?string, answered: int, total: int, twk: int, tiu: int, tkp: int, score: int, in_progress: bool}>
     */
    private function skdRows(): array
    {
        $attempts = ExamAttempt::query()
            ->where('event_id', $this->eventId)
            ->when($this->sessionId, fn ($query) => $query->where('event_session_id', $this->sessionId))
            ->with([
                'user:id,name,instansi_id',
                'user.instansi:id,nama',
                'eventSession:id,name',
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
                    'name' => $attempt->resolvedDisplayName(),
                    'instansi' => $attempt->user?->instansi?->nama,
                    'session' => $attempt->eventSession?->name,
                    'answered' => $answered,
                    'total' => $total,
                    'twk' => $scores['twk'],
                    'tiu' => $scores['tiu'],
                    'tkp' => $scores['tkp'],
                    'score' => $scores['total'],
                    'in_progress' => $inProgress,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * SKB-only board: rows come from the event roster (not just attempts) so
     * a peserta who hasn't started yet still appears as "Belum Ujian".
     */
    private function skbRows(): array
    {
        $participants = EventParticipant::query()
            ->where('event_id', $this->eventId)
            ->when($this->sessionId, fn ($query) => $query->where('event_session_id', $this->sessionId))
            ->with(['eventSession:id,name', 'jabatanSkb:id,name'])
            ->get();

        $attempts = SkbExamAttempt::query()
            ->where('event_id', $this->eventId)
            ->with(['answers:id,skb_exam_attempt_id,selected_option_id'])
            ->get()
            ->keyBy('user_id');

        return $participants
            ->map(function (EventParticipant $participant) use ($attempts) {
                $attempt = $attempts->get($participant->user_id);
                $jabatan = $participant->jabatanSkb?->name ?? $participant->jabatan_label;

                if ($attempt === null) {
                    return [
                        'name' => $participant->name,
                        'jabatan' => $jabatan,
                        'session' => $participant->eventSession?->name,
                        'answered' => 0,
                        'total' => 0,
                        'benar' => 0,
                        'score' => 0,
                        'status_label' => 'Belum Ujian',
                        'in_progress' => false,
                    ];
                }

                $total = $attempt->answers->count();
                $answered = $attempt->answers->whereNotNull('selected_option_id')->count();
                $inProgress = $attempt->status === ExamAttemptStatus::InProgress;

                return [
                    'name' => $participant->name,
                    'jabatan' => $jabatan,
                    'session' => $participant->eventSession?->name,
                    'answered' => $answered,
                    'total' => $total,
                    'benar' => $inProgress ? 0 : (int) $attempt->correct_count,
                    'score' => $inProgress ? 0 : (int) $attempt->total_score,
                    'status_label' => $inProgress ? 'Sedang Ujian' : 'Selesai',
                    'in_progress' => $inProgress,
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * Called by wire:poll. Refreshes the board and — if the event was deleted or
     * its public livescore switched off while this screen stayed open — leaves
     * the board so a deleted event's participants stop showing. A fresh visit
     * already 404s via route binding.
     */
    public function refreshBoard(): void
    {
        if ($this->event === null) {
            $this->redirect(route('public.livescore.index'), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.public.live-score-show', [
            'event' => $this->event,
            'category' => $this->activeBoard(),
            'showExamTypePicker' => $this->supportsBothBoards(),
        ]);
    }
}
