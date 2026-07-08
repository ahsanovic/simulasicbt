<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\QuestionOption;
use App\Services\ExamPsychologyTelemetryService;
use App\Services\ExamService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ruang Ujian')]
class ExamRoom extends Component
{
    #[Locked]
    public int $examId;

    #[Locked]
    public string $examTitle;

    #[Locked]
    public int $attemptId;

    #[Locked]
    public int $attemptExpiresAt;

    #[Locked]
    public int $currentIndex = 0;

    /** @var list<array{id: int, sort_order: int, question_id: int, selected_option_id: ?int, is_marked: bool}> */
    public array $answerStates = [];

    /** @var list<int> */
    public array $currentOptionIds = [];

    public ?int $selectedOptionId = null;

    /** @var array<string, int> */
    public array $questionDurations = [];

    /** @var array<string, array{first_option_id: ?int, change_count: int, last_change_remaining_seconds: ?int}> */
    public array $answerBehavior = [];

    public ?int $questionTimerStartedAt = null;

    public bool $showLastQuestionModal = false;

    public function mount(Exam $exam): void
    {
        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->with(['answers' => fn ($query) => $query->select(
                'id',
                'exam_attempt_id',
                'question_id',
                'sort_order',
                'selected_option_id',
                'is_marked',
            )])
            ->firstOrFail();

        if (! $attempt->isActive()) {
            $attempt->update(['status' => ExamAttemptStatus::Expired]);
            $this->redirect(route('peserta.dashboard'), navigate: true);

            return;
        }

        $this->examId = $exam->id;
        $this->examTitle = $exam->title;
        $this->attemptId = $attempt->id;
        $this->attemptExpiresAt = $attempt->expires_at->timestamp;
        $this->answerStates = $attempt->answers
            ->sortBy(fn (ExamAnswer $answer) => $answer->sort_order ?: 999)
            ->values()
            ->map(fn (ExamAnswer $answer) => [
                'id' => $answer->id,
                'sort_order' => (int) $answer->sort_order,
                'question_id' => $answer->question_id,
                'selected_option_id' => $answer->selected_option_id,
                'is_marked' => (bool) $answer->is_marked,
            ])
            ->all();

        $stored = $attempt->question_duration ?? [];
        $this->questionDurations = collect($stored['by_sort_order'] ?? [])
            ->mapWithKeys(fn ($seconds, $key) => [(string) $key => max(0, (int) $seconds)])
            ->all();

        $this->loadAnswerBehavior($attempt);

        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
    }

    public function getAnswersProperty()
    {
        return collect($this->answerStates)->map(fn (array $state) => (object) $state);
    }

    #[Computed]
    public function currentAnswer(): ?ExamAnswer
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return null;
        }

        return ExamAnswer::query()
            ->whereKey($state['id'])
            ->where('exam_attempt_id', $this->attemptId)
            ->with(['question.options', 'question.subject'])
            ->first();
    }

    public function getAnsweredCountProperty(): int
    {
        return collect($this->answerStates)
            ->whereNotNull('selected_option_id')
            ->count();
    }

    public function getUnansweredCountProperty(): int
    {
        return count($this->answerStates) - $this->answeredCount;
    }

    public function getProgressPercentProperty(): int
    {
        if ($this->answerStates === []) {
            return 0;
        }

        return (int) round(($this->answeredCount / count($this->answerStates)) * 100);
    }

    public function getRemainingSecondsProperty(): int
    {
        return max(0, $this->attemptExpiresAt - now()->timestamp);
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
        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        $optionId = $this->selectedOptionId;

        if ($optionId !== null && ! $this->isValidOptionForCurrentQuestion($optionId)) {
            $optionId = null;
        }

        $this->trackAnswerBehavior($state['selected_option_id'], $optionId);

        ExamAnswer::query()
            ->whereKey($state['id'])
            ->where('exam_attempt_id', $this->attemptId)
            ->update([
                'selected_option_id' => $optionId,
                'answered_at' => $optionId ? now() : null,
            ]);

        $this->syncAnswerInMemory($optionId);
    }

    public function toggleMark(): void
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        $newMarked = ! $state['is_marked'];

        ExamAnswer::query()
            ->whereKey($state['id'])
            ->where('exam_attempt_id', $this->attemptId)
            ->update([
                'is_marked' => $newMarked,
            ]);

        $this->syncMarkedInMemory($newMarked);
    }

    public function goToQuestion(int $index): void
    {
        if ($index < 0 || $index >= count($this->answerStates)) {
            return;
        }

        $this->showLastQuestionModal = false;
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

        if ($this->currentIndex < count($this->answerStates) - 1) {
            $this->currentIndex++;
            $this->loadCurrentAnswer();
            $this->startQuestionTimer();
        } else {
            $this->showLastQuestionModal = true;
        }
    }

    public function closeLastQuestionModal(): void
    {
        $this->showLastQuestionModal = false;
    }

    public function goBackFromLastQuestionModal(): void
    {
        $this->showLastQuestionModal = false;

        $firstUnansweredIndex = collect($this->answerStates)
            ->search(fn (array $state) => $state['selected_option_id'] === null);

        if ($firstUnansweredIndex !== false) {
            $this->goToQuestion($firstUnansweredIndex);

            return;
        }

        if ($this->currentIndex > 0) {
            $this->goToQuestion(0);
        }
    }

    public function submitExam(ExamService $examService): void
    {
        $this->showLastQuestionModal = false;
        $this->saveAnswer();
        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();
        $this->persistTelemetries();
        $attempt = $examService->submitAttempt($this->resolveAttempt(), auth()->user());
        session()->flash('show_result_attempt_id', $attempt->id);
        $this->redirect(route('peserta.history'), navigate: true);
    }

    public function checkExpiry(): void
    {
        if ($this->remainingSeconds <= 0) {
            $this->accumulateCurrentQuestionDuration();
            $this->persistAttemptMetadata();
            $this->persistTelemetries();
            $attempt = app(ExamService::class)->submitAttempt($this->resolveAttempt(), auth()->user());
            session()->flash('show_result_attempt_id', $attempt->id);
            session()->flash('error', 'Waktu ujian habis. Jawaban otomatis dikumpulkan.');
            $this->redirect(route('peserta.history'), navigate: true);
        }
    }

    private function currentAnswerState(): ?array
    {
        return $this->answerStates[$this->currentIndex] ?? null;
    }

    private function resolveAttempt(): ExamAttempt
    {
        return ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        return in_array($optionId, $this->currentOptionIds, true);
    }

    private function syncAnswerInMemory(?int $optionId): void
    {
        if (! isset($this->answerStates[$this->currentIndex])) {
            return;
        }

        $this->answerStates[$this->currentIndex]['selected_option_id'] = $optionId;
        $this->invalidateAnswerComputedProperties();
    }

    private function syncMarkedInMemory(bool $isMarked): void
    {
        if (! isset($this->answerStates[$this->currentIndex])) {
            return;
        }

        $this->answerStates[$this->currentIndex]['is_marked'] = $isMarked;
        $this->invalidateAnswerComputedProperties();
    }

    private function invalidateAnswerComputedProperties(): void
    {
        unset($this->answers, $this->currentAnswer, $this->answeredCount, $this->unansweredCount, $this->progressPercent);
    }

    private function loadCurrentAnswer(): void
    {
        $state = $this->currentAnswerState();

        $this->selectedOptionId = $state['selected_option_id'] ?? null;
        unset($this->currentAnswer);

        if ($state === null) {
            $this->currentOptionIds = [];

            return;
        }

        $this->currentOptionIds = QuestionOption::query()
            ->where('question_id', $state['question_id'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function startQuestionTimer(): void
    {
        $this->questionTimerStartedAt = now()->timestamp;
    }

    private function accumulateCurrentQuestionDuration(): void
    {
        $state = $this->currentAnswerState();

        if ($state === null || $this->questionTimerStartedAt === null) {
            return;
        }

        $elapsed = max(0, now()->timestamp - $this->questionTimerStartedAt);
        $key = (string) $state['sort_order'];
        $this->questionDurations[$key] = ($this->questionDurations[$key] ?? 0) + $elapsed;
        $this->questionTimerStartedAt = null;
    }

    private function persistAttemptMetadata(): void
    {
        ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->update([
                'question_duration' => ['by_sort_order' => $this->questionDurations],
                'answer_behavior' => ['by_sort_order' => $this->answerBehavior],
            ]);
    }

    private function trackAnswerBehavior(?int $previousOptionId, ?int $newOptionId): void
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        $key = (string) $state['sort_order'];

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
        $attempt = ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->with(['answers.question.options', 'answers.selectedOption'])
            ->firstOrFail();

        app(ExamPsychologyTelemetryService::class)->persistForAttempt(
            $attempt,
            $this->questionDurations,
            $this->answerBehavior,
            $this->remainingSeconds,
        );
    }

    private function loadAnswerBehavior(ExamAttempt $attempt): void
    {
        $stored = $attempt->answer_behavior ?? [];
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
