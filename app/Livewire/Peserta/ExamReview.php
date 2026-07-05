<?php

namespace App\Livewire\Peserta;

use App\Enums\AnswerReviewOutcome;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamTimeManagementService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'history', 'showNav' => true])]
#[Title('Kunci Jawaban dan Pembahasan')]
class ExamReview extends Component
{
    #[Locked]
    public ExamAttempt $attempt;

    #[Locked]
    public int $currentIndex = 0;

    public bool $showTimeManagementModal = false;

    public function mount(ExamAttempt $attempt): void
    {
        $this->attempt = ExamAttempt::findReviewableForUser($attempt->id, auth()->id());
        $this->ensureCurrentQuestionExists();
    }

    public function getAnswersProperty()
    {
        return $this->attempt->answers
            ->sortBy(fn (ExamAnswer $answer) => $answer->sort_order ?: 999)
            ->values();
    }

    public function getCurrentAnswerProperty(): ?ExamAnswer
    {
        return $this->answers[$this->currentIndex] ?? null;
    }

    public function getReviewStatsProperty(): array
    {
        $outcomes = $this->answers->map(fn (ExamAnswer $answer) => $answer->reviewOutcome());

        return [
            'correct' => $outcomes->filter->isPositive()->count(),
            'incorrect' => $outcomes->reject(fn (AnswerReviewOutcome $outcome) => $outcome->isPositive() || $outcome === AnswerReviewOutcome::Unanswered)->count(),
            'unanswered' => $outcomes->filter(fn (AnswerReviewOutcome $outcome) => $outcome === AnswerReviewOutcome::Unanswered)->count(),
        ];
    }

    public function getTimeAnalysisProperty(): array
    {
        return app(ExamTimeManagementService::class)->analyzeAttempt($this->attempt);
    }

    public function getCurrentQuestionDurationSecondsProperty(): int
    {
        if (! $this->currentAnswer) {
            return 0;
        }

        $key = (string) $this->currentAnswer->sort_order;
        $analysis = $this->timeAnalysis;

        return $analysis['by_sort_order'][$key]['seconds'] ?? 0;
    }

    public function getCurrentQuestionDurationStatusProperty(): ?array
    {
        if (! $this->currentAnswer) {
            return null;
        }

        $key = (string) $this->currentAnswer->sort_order;
        $analysis = $this->timeAnalysis;

        return $analysis['by_sort_order'][$key]['status'] ?? null;
    }

    public function openTimeManagementModal(): void
    {
        $this->showTimeManagementModal = true;
    }

    public function closeTimeManagementModal(): void
    {
        $this->showTimeManagementModal = false;
    }

    public function goToQuestion(int $index): void
    {
        $this->tryNavigateToIndex($index, notifyWhenMissing: true);
    }

    public function previous(): void
    {
        if ($this->currentIndex <= 0) {
            return;
        }

        $this->tryNavigateToIndex($this->currentIndex - 1, notifyWhenMissing: true);
    }

    public function next(): void
    {
        if ($this->currentIndex >= $this->answers->count() - 1) {
            return;
        }

        $this->tryNavigateToIndex($this->currentIndex + 1, notifyWhenMissing: true);
    }

    private function ensureCurrentQuestionExists(): void
    {
        if ($this->answers->isEmpty()) {
            return;
        }

        if ($this->answers[$this->currentIndex]?->question) {
            return;
        }

        foreach ($this->answers as $index => $answer) {
            if ($answer->question) {
                $this->currentIndex = $index;

                return;
            }
        }
    }

    private function tryNavigateToIndex(int $index, bool $notifyWhenMissing = false): bool
    {
        if ($index < 0 || $index >= $this->answers->count()) {
            return false;
        }

        $answer = $this->answers[$index];

        if (! $answer->question) {
            if ($notifyWhenMissing) {
                session()->flash('warning', 'Soal tidak tersedia karena telah dihapus.');
            }

            return false;
        }

        $this->currentIndex = $index;

        return true;
    }

    public function render()
    {
        return view('livewire.peserta.exam-review', [
            'passingGrades' => exam_passing_grades(),
            'scoreMax' => exam_score_max(),
        ]);
    }
}
