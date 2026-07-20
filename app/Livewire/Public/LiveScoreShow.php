<?php

namespace App\Livewire\Public;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\ExamAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Livescore')]
class LiveScoreShow extends Component
{
    public Event $event;

    public ?int $sessionId = null;

    public function mount(Event $event): void
    {
        abort_unless($event->public_livescore, 404);

        $this->event = $event->load('exam:id,title,duration_minutes');
    }

    #[Computed]
    public function sessions()
    {
        return $this->event->sessions()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return list<array{rank: int, name: string, instansi: ?string, session: ?string, answered: int, total: int, score: int, in_progress: bool}>
     */
    #[Computed]
    public function rows(): array
    {
        $attempts = ExamAttempt::query()
            ->where('event_id', $this->event->id)
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
                    'name' => $attempt->user?->name ?? 'Peserta',
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

    public function render()
    {
        return view('livewire.public.live-score-show');
    }
}
