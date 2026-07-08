<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamPsychologyTelemetryService;
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

    /** @var array<string, array{first_option_id: ?int, change_count: int, last_change_remaining_seconds: ?int}> */
    public array $answerBehavior = [];

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

        $this->loadAnswerBehavior();

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

        $optionId = $this->selectedOptionId;

        if ($optionId !== null && ! $this->isValidOptionForCurrentQuestion($optionId)) {
            $optionId = null;
        }

        $this->trackAnswerBehavior($this->currentAnswer->selected_option_id, $optionId);

        ExamAnswer::query()
            ->whereKey($this->currentAnswer->id)
            ->where('exam_attempt_id', $this->attempt->id)
            ->update([
                'selected_option_id' => $optionId,
                'answered_at' => $optionId ? now() : null,
            ]);

        $this->syncAnswerInMemory($optionId);
    }

    public function toggleMark(): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $newMarked = ! $this->currentAnswer->is_marked;

        ExamAnswer::query()
            ->whereKey($this->currentAnswer->id)
            ->where('exam_attempt_id', $this->attempt->id)
            ->update([
                'is_marked' => $newMarked,
            ]);

        $this->syncMarkedInMemory($newMarked);
    }

    public function goToQuestion(int $index): void
    {
        if ($index < 0 || $index >= $this->answers->count()) {
            return;
        }

        $this->saveAnswer();
        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();
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
        $this->persistAttemptMetadata();

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
        $this->persistAttemptMetadata();
        $this->persistTelemetries();
        $attempt = $examService->submitAttempt($this->attempt, auth()->user());
        session()->flash('show_result_attempt_id', $attempt->id);
        $this->redirect(route('peserta.history'), navigate: true);
    }

    public function checkExpiry(): void
    {
        if ($this->remainingSeconds <= 0) {
            $this->accumulateCurrentQuestionDuration();
            $this->persistAttemptMetadata();
            $this->persistTelemetries();
            $attempt = app(ExamService::class)->submitAttempt($this->attempt, auth()->user());
            session()->flash('show_result_attempt_id', $attempt->id);
            session()->flash('error', 'Waktu ujian habis. Jawaban otomatis dikumpulkan.');
            $this->redirect(route('peserta.history'), navigate: true);
        }
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        if (! $this->currentAnswer) {
            return false;
        }

        return $this->currentAnswer->question->options->contains('id', $optionId);
    }

    private function syncAnswerInMemory(?int $optionId): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $answer = $this->attempt->answers->firstWhere('id', $this->currentAnswer->id);

        if ($answer) {
            $answer->selected_option_id = $optionId;
            $answer->answered_at = $optionId ? now() : null;
        }

        $this->invalidateAnswerComputedProperties();
    }

    private function syncMarkedInMemory(bool $isMarked): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $answer = $this->attempt->answers->firstWhere('id', $this->currentAnswer->id);

        if ($answer) {
            $answer->is_marked = $isMarked;
        }

        $this->invalidateAnswerComputedProperties();
    }

    private function invalidateAnswerComputedProperties(): void
    {
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

    private function persistAttemptMetadata(): void
    {
        $durationPayload = ['by_sort_order' => $this->questionDurations];
        $behaviorPayload = ['by_sort_order' => $this->answerBehavior];

        ExamAttempt::query()
            ->whereKey($this->attempt->id)
            ->where('user_id', auth()->id())
            ->update([
                'question_duration' => $durationPayload,
                'answer_behavior' => $behaviorPayload,
            ]);

        $this->attempt->question_duration = $durationPayload;
        $this->attempt->answer_behavior = $behaviorPayload;
    }

    private function trackAnswerBehavior(?int $previousOptionId, ?int $newOptionId): void
    {
        if (! $this->currentAnswer) {
            return;
        }

        $key = (string) $this->currentAnswer->sort_order;

        if (! isset($this->answerBehavior[$key])) {
            $this->answerBehavior[$key] = [
                'first_option_id' => $newOptionId,
                'change_count' => 0,
                'last_change_remaining_seconds' => null,
            ];

            return;
        }

        if ($newOptionId === null || $previousOptionId === null || $newOptionId === $previousOptionId) {
            return;
        }

        $this->answerBehavior[$key]['change_count']++;
        $this->answerBehavior[$key]['last_change_remaining_seconds'] = $this->remainingSeconds;
    }

    private function persistTelemetries(): void
    {
        app(ExamPsychologyTelemetryService::class)->persistForAttempt(
            $this->attempt,
            $this->questionDurations,
            $this->answerBehavior,
            $this->remainingSeconds,
        );
    }

    private function loadAnswerBehavior(): void
    {
        $stored = $this->attempt->answer_behavior ?? [];
        $this->answerBehavior = collect($stored['by_sort_order'] ?? [])
            ->mapWithKeys(fn (array $behavior, $key) => [
                (string) $key => [
                    'first_option_id' => $behavior['first_option_id'] ?? null,
                    'change_count' => max(0, (int) ($behavior['change_count'] ?? 0)),
                    'last_change_remaining_seconds' => $behavior['last_change_remaining_seconds'] ?? null,
                ],
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.peserta.exam-room');
    }
}
