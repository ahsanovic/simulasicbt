<?php

namespace App\Livewire\Public;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Livescore')]
class LiveScoreShow extends Component
{
    public int $eventId;

    public ?int $sessionId = null;

    public function mount(Event $event): void
    {
        abort_unless($event->public_livescore, 404);

        $this->eventId = $event->id;
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

    /**
     * @return list<array{rank: int, name: string, instansi: ?string, session: ?string, answered: int, total: int, score: int, in_progress: bool}>
     */
    /**
     * Participants whose time ran out while offline never submitted themselves,
     * so close them out before reporting status.
     */
    private function closeExpiredAttempts(): void
    {
        $expired = ExamAttempt::query()
            ->where('event_id', $this->eventId)
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
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
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
        return view('livewire.public.live-score-show', ['event' => $this->event]);
    }
}
