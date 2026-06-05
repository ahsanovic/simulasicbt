<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ruang Ujian')]
class ExamRoom extends Component
{
    public Exam $exam;

    public ExamAttempt $attempt;

    public int $currentIndex = 0;

    public ?int $selectedOptionId = null;

    public function mount(Exam $exam): void
    {
        $this->exam = $exam->load('questions.subject');

        $this->attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->with(['answers.question.options', 'answers.question.subject'])
            ->firstOrFail();

        if (! $this->attempt->isActive()) {
            $this->attempt->update(['status' => ExamAttemptStatus::Expired]);
            $this->redirect(route('peserta.dashboard'), navigate: true);

            return;
        }

        $this->loadCurrentAnswer();
    }

    public function getAnswersProperty()
    {
        return $this->attempt->answers
            ->sortBy(fn ($answer) => $answer->sort_order ?: 999)
            ->values();
    }

    public function getCurrentAnswerProperty(): ?ExamAnswer
    {
        return $this->answers[$this->currentIndex] ?? null;
    }

    public function getAnsweredCountProperty(): int
    {
        return $this->answers->whereNotNull('selected_option_id')->count();
    }

    public function getUnansweredCountProperty(): int
    {
        return $this->answers->count() - $this->answeredCount;
    }

    public function getProgressPercentProperty(): int
    {
        if ($this->answers->isEmpty()) {
            return 0;
        }

        return (int) round(($this->answeredCount / $this->answers->count()) * 100);
    }

    public function getRemainingSecondsProperty(): int
    {
        if (now()->gte($this->attempt->expires_at)) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->attempt->expires_at);
    }

    public function selectOption(int $optionId): void
    {
        $this->selectedOptionId = $optionId;
    }

    public function saveAnswer(): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        ExamAnswer::query()->whereKey($this->currentAnswer->id)->update([
            'selected_option_id' => $this->selectedOptionId,
            'answered_at' => $this->selectedOptionId ? now() : null,
        ]);

        $this->refreshAttemptData();
    }

    public function toggleMark(): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $newMarked = ! $this->currentAnswer->is_marked;

        ExamAnswer::query()->whereKey($this->currentAnswer->id)->update([
            'is_marked' => $newMarked,
        ]);

        $this->refreshAttemptData();
    }

    public function goToQuestion(int $index): void
    {
        $this->saveAnswer();
        $this->currentIndex = $index;
        $this->loadCurrentAnswer();
    }

    public function previous(): void
    {
        if ($this->currentIndex > 0) {
            $this->goToQuestion($this->currentIndex - 1);
        }
    }

    public function next(): void
    {
        $this->saveAnswer();

        if ($this->currentIndex < $this->answers->count() - 1) {
            $this->currentIndex++;
            $this->loadCurrentAnswer();
        }
    }

    public function submitExam(ExamService $examService): void
    {
        $this->saveAnswer();
        $attempt = $examService->submitAttempt($this->attempt);
        session()->flash('show_result_attempt_id', $attempt->id);
        $this->redirect(route('peserta.history'), navigate: true);
    }

    public function checkExpiry(): void
    {
        if ($this->remainingSeconds <= 0) {
            $attempt = app(ExamService::class)->submitAttempt($this->attempt);
            session()->flash('show_result_attempt_id', $attempt->id);
            session()->flash('error', 'Waktu ujian habis. Jawaban otomatis dikumpulkan.');
            $this->redirect(route('peserta.history'), navigate: true);
        }
    }

    private function refreshAttemptData(): void
    {
        $this->attempt = ExamAttempt::query()
            ->whereKey($this->attempt->id)
            ->with(['answers.question.options', 'answers.question.subject'])
            ->firstOrFail();

        unset($this->answers, $this->currentAnswer, $this->answeredCount, $this->unansweredCount, $this->progressPercent);
    }

    private function loadCurrentAnswer(): void
    {
        unset($this->currentAnswer);
        $this->selectedOptionId = $this->currentAnswer?->selected_option_id;
    }

    public function render()
    {
        return view('livewire.peserta.exam-room');
    }
}
