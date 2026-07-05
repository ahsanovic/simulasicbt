<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ruang Ujian')]
class ExamRoom extends Component
{
    #[Locked]
    public Exam $exam;

    #[Locked]
    public ExamAttempt $attempt;

    #[Locked]
    public int $currentIndex = 0;

    public ?int $selectedOptionId = null;

    /** @var array<string, int> */
    public array $questionDurations = [];

    public ?int $questionTimerStartedAt = null;

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

        $stored = $this->attempt->question_duration ?? [];
        $this->questionDurations = collect($stored['by_sort_order'] ?? [])
            ->mapWithKeys(fn ($seconds, $key) => [(string) $key => max(0, (int) $seconds)])
            ->all();

        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
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
        return $this->attempt->remainingSeconds();
    }

    public function selectOption(int $optionId): void
    {
        if (! $this->isValidOptionForCurrentQuestion($optionId)) {
            return;
        }

        $this->selectedOptionId = $optionId;
    }

    public function saveAnswer(): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $this->authorizedAttempt();

        $optionId = $this->selectedOptionId;

        if ($optionId !== null && ! $this->isValidOptionForCurrentQuestion($optionId)) {
            $optionId = null;
        }

        ExamAnswer::query()
            ->whereKey($this->currentAnswer->id)
            ->where('exam_attempt_id', $this->attempt->id)
            ->update([
                'selected_option_id' => $optionId,
                'answered_at' => $optionId ? now() : null,
            ]);

        $this->refreshAttemptData();
    }

    public function toggleMark(): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $this->authorizedAttempt();

        $newMarked = ! $this->currentAnswer->is_marked;

        ExamAnswer::query()
            ->whereKey($this->currentAnswer->id)
            ->where('exam_attempt_id', $this->attempt->id)
            ->update([
                'is_marked' => $newMarked,
            ]);

        $this->refreshAttemptData();
    }

    public function goToQuestion(int $index): void
    {
        if ($index < 0 || $index >= $this->answers->count()) {
            return;
        }

        $this->saveAnswer();
        $this->accumulateCurrentQuestionDuration();
        $this->persistQuestionDurations();
        $this->currentIndex = $index;
        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
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
        $this->accumulateCurrentQuestionDuration();
        $this->persistQuestionDurations();

        if ($this->currentIndex < $this->answers->count() - 1) {
            $this->currentIndex++;
            $this->loadCurrentAnswer();
            $this->startQuestionTimer();
        }
    }

    public function submitExam(ExamService $examService): void
    {
        $this->saveAnswer();
        $this->accumulateCurrentQuestionDuration();
        $this->persistQuestionDurations();
        $attempt = $examService->submitAttempt($this->attempt, auth()->user());
        session()->flash('show_result_attempt_id', $attempt->id);
        $this->redirect(route('peserta.history'), navigate: true);
    }

    public function checkExpiry(): void
    {
        if ($this->remainingSeconds <= 0) {
            $this->accumulateCurrentQuestionDuration();
            $this->persistQuestionDurations();
            $attempt = app(ExamService::class)->submitAttempt($this->attempt, auth()->user());
            session()->flash('show_result_attempt_id', $attempt->id);
            session()->flash('error', 'Waktu ujian habis. Jawaban otomatis dikumpulkan.');
            $this->redirect(route('peserta.history'), navigate: true);
        }
    }

    private function authorizedAttempt(): ExamAttempt
    {
        return ExamAttempt::query()
            ->whereKey($this->attempt->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->firstOrFail();
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        if (! $this->currentAnswer) {
            return false;
        }

        return $this->currentAnswer->question->options->contains('id', $optionId);
    }

    private function refreshAttemptData(): void
    {
        $this->attempt = ExamAttempt::query()
            ->whereKey($this->attempt->id)
            ->where('user_id', auth()->id())
            ->with(['answers.question.options', 'answers.question.subject'])
            ->firstOrFail();

        unset($this->answers, $this->currentAnswer, $this->answeredCount, $this->unansweredCount, $this->progressPercent);
    }

    private function loadCurrentAnswer(): void
    {
        unset($this->currentAnswer);
        $this->selectedOptionId = $this->currentAnswer?->selected_option_id;
    }

    private function startQuestionTimer(): void
    {
        $this->questionTimerStartedAt = now()->timestamp;
    }

    private function accumulateCurrentQuestionDuration(): void
    {
        if (! $this->currentAnswer || $this->questionTimerStartedAt === null) {
            return;
        }

        $elapsed = max(0, now()->timestamp - $this->questionTimerStartedAt);
        $key = (string) $this->currentAnswer->sort_order;
        $this->questionDurations[$key] = ($this->questionDurations[$key] ?? 0) + $elapsed;
        $this->questionTimerStartedAt = null;
    }

    private function persistQuestionDurations(): void
    {
        $payload = ['by_sort_order' => $this->questionDurations];

        ExamAttempt::query()
            ->whereKey($this->attempt->id)
            ->where('user_id', auth()->id())
            ->update(['question_duration' => $payload]);

        $this->attempt->question_duration = $payload;
    }

    public function render()
    {
        return view('livewire.peserta.exam-room');
    }
}
