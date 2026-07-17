<?php

namespace App\Livewire\Admin\Events;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\ExamAttempt;
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

    public function mount(Event $event, EventSession $session): void
    {
        abort_unless($session->event_id === $event->id, 404);

        $this->event = $event->load('exam:id,title,duration_minutes');
        $this->session = $session;
    }

    /**
     * @return list<array{attempt_id: int, name: string, instansi: ?string, answered: int, total: int, score: int, status: ExamAttemptStatus, in_progress: bool, remaining: ?string, submitted_at: ?string}>
     */
    #[Computed]
    public function rows(): array
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

                $score = $inProgress
                    ? $attempt->calculateScores()['total']
                    : (int) $attempt->total_score;

                return [
                    'attempt_id' => $attempt->id,
                    'name' => $attempt->user?->name ?? 'Peserta',
                    'instansi' => $attempt->user?->instansi?->nama,
                    'answered' => $answered,
                    'total' => $total,
                    'score' => $score,
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

    /** @return list<string> */
    private function inProgressAttemptIds(): array
    {
        return $this->session->attempts()
            ->where('status', ExamAttemptStatus::InProgress)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->inProgressAttemptIds() : [];
    }

    public function addTime(int $attemptId): void
    {
        $minutes = $this->normalizedMinutes();

        $attempt = $this->session->attempts()
            ->whereKey($attemptId)
            ->where('status', ExamAttemptStatus::InProgress)
            ->with('user:id,name')
            ->first();

        if ($attempt === null) {
            session()->flash('error', 'Peserta tidak sedang mengerjakan ujian.');

            return;
        }

        $this->extendAttempt($attempt, $minutes);
        unset($this->rows, $this->summary);

        session()->flash('success', "Waktu +{$minutes} menit untuk {$attempt->user?->name}.");
    }

    public function addTimeToSelected(): void
    {
        $minutes = $this->normalizedMinutes();
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            session()->flash('error', 'Belum ada peserta yang dipilih.');

            return;
        }

        $attempts = $this->session->attempts()
            ->whereIn('id', $ids)
            ->where('status', ExamAttemptStatus::InProgress)
            ->get();

        foreach ($attempts as $attempt) {
            $this->extendAttempt($attempt, $minutes);
        }

        $this->selected = [];
        $this->selectAll = false;
        unset($this->rows, $this->summary);

        session()->flash('success', "Waktu +{$minutes} menit untuk {$attempts->count()} peserta.");
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
