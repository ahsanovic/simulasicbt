<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Concerns\EnforcesExamDeadline;
use App\Livewire\Concerns\VersionsExamAnswers;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Services\SkbExamService;
use App\Support\ExamQuestionCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ujian SKB')]
class SkbExamRoom extends Component
{
    use EnforcesExamDeadline;
    use VersionsExamAnswers;

    #[Locked]
    public int $attemptId;

    #[Locked]
    public int $attemptExpiresAt;

    #[Locked]
    public int $currentIndex = 0;

    /**
     * The option highlighted on screen. Bound with a deferred wire:model, so
     * picking an answer is instant in the browser and only travels to the
     * server together with "Simpan" / navigation — no request per click.
     */
    public ?int $selectedOptionId = null;

    /** Saved option of the current question, for the browser-side "Tersimpan" state. */
    #[Locked]
    public ?int $savedOptionId = null;

    /** Unanswered count by saved answers, for the browser-side finish confirmation. */
    #[Locked]
    public int $unansweredSaved = 0;

    /** @var array<int, array{id: int, sort_order: int, question_id: int, selected_option_id: ?int, is_marked: bool}> */
    #[Locked]
    public array $answerStates = [];

    public function mount(): void
    {
        $attempt = SkbExamAttempt::query()
            ->where('user_id', Auth::id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->latest('id')
            ->with('answers')
            ->first();

        if ($attempt === null) {
            $this->redirect(route('peserta.mode-ujian.dashboard'), navigate: false);

            return;
        }

        if (! $attempt->isActive()) {
            $attempt = app(SkbExamService::class)->submitAttempt($attempt);
            $this->redirect(route('peserta.mode-ujian.skb-result', $attempt), navigate: false);

            return;
        }

        $this->attemptId = $attempt->id;
        $this->attemptExpiresAt = $attempt->expires_at->timestamp;
        $this->answerVersionBase = (int) $attempt->answers->max('answer_version');
        $this->answerStates = $attempt->answers->map(fn ($answer) => [
            'id' => $answer->id,
            'sort_order' => $answer->sort_order,
            'question_id' => $answer->skb_question_id,
            'selected_option_id' => $answer->selected_option_id,
            'is_marked' => (bool) $answer->is_marked,
        ])->all();

        // Back after a refresh, lost connection or anti-cheat logout: continue
        // at the first question without a saved answer, not at question 1.
        $firstUnanswered = collect($this->answerStates)->search(fn (array $state) => $state['selected_option_id'] === null);
        $this->currentIndex = $firstUnanswered === false ? 0 : $firstUnanswered;
        $this->selectedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
    }

    public function getRemainingSecondsProperty(): int
    {
        return $this->deadlineRemainingSeconds();
    }

    public function getAnswersProperty()
    {
        return collect($this->answerStates);
    }

    public function getAnsweredCountProperty(): int
    {
        return collect($this->answerStates)->filter(fn ($state) => $state['selected_option_id'] !== null)->count();
    }

    public function getUnansweredCountProperty(): int
    {
        return count($this->answerStates) - $this->answeredCount;
    }

    /** The pick on screen is the one stored for the current question. */
    public function getCurrentPickSavedProperty(): bool
    {
        return $this->selectedOptionId !== null
            && $this->selectedOptionId === ($this->answerStates[$this->currentIndex]['selected_option_id'] ?? null);
    }

    /** Unanswered count once "Selesai Ujian" also saves the pick on screen. */
    public function getUnansweredOnSubmitCountProperty(): int
    {
        $pendingPick = $this->selectedOptionId !== null
            && ($this->answerStates[$this->currentIndex]['selected_option_id'] ?? null) === null;

        return max(0, $this->unansweredCount - ($pendingPick ? 1 : 0));
    }

    public function getSubmitConfirmMessageProperty(): string
    {
        $unanswered = $this->unansweredOnSubmitCount;

        return $unanswered > 0
            ? "Masih ada {$unanswered} soal belum dijawab. Yakin ingin menyelesaikan ujian SKB sekarang? Skor akan disimpan."
            : 'Semua soal sudah dijawab. Selesaikan ujian SKB ini? Skor akan disimpan.';
    }

    public function getProgressPercentProperty(): int
    {
        $total = count($this->answerStates);

        return $total > 0 ? (int) round(($this->answeredCount / $total) * 100) : 0;
    }

    /** The question on screen with its options, from the shared question cache. */
    #[Computed]
    public function currentQuestion(): ?SkbQuestion
    {
        $questionId = $this->answerStates[$this->currentIndex]['question_id'] ?? null;

        return $questionId === null ? null : ExamQuestionCache::skb($questionId);
    }

    public function saveAnswer(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        $this->persistCurrentAnswer();
    }

    /**
     * Save the on-screen pick of the current question. Returns false when it
     * was refused (time is up, or stale — the room is then being reloaded);
     * the caller must stop instead of moving on.
     */
    private function persistCurrentAnswer(bool $reloadIfStale = true): bool
    {
        $state = $this->answerStates[$this->currentIndex] ?? null;

        if ($state === null) {
            return true;
        }

        $optionId = $this->selectedOptionId;

        // The pick arrives straight from the browser (deferred wire:model):
        // ignore one that does not belong to this question, keep the saved one.
        if ($optionId !== null && ! $this->isValidOptionForCurrentQuestion($optionId)) {
            $optionId = $state['selected_option_id'];
        }

        $version = $this->incomingAnswerVersion();
        $saved = app(SkbExamService::class)->saveAnswer(
            $this->resolveAttempt(),
            $state['question_id'],
            $optionId,
            $version,
        );

        if ($saved === false) {
            $this->checkExpiry();

            return false;
        }

        if ($saved === null) {
            if ($reloadIfStale) {
                $this->reloadForStaleAnswer('skb_exam_answers', $state['id'], $version);
            }

            return false;
        }

        $this->answerStates[$this->currentIndex]['selected_option_id'] = $optionId;

        return true;
    }

    public function toggleMark(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        $this->answerStates[$this->currentIndex]['is_marked'] = ! $this->answerStates[$this->currentIndex]['is_marked'];

        app(SkbExamService::class)->toggleMark(
            $this->resolveAttempt(),
            $this->answerStates[$this->currentIndex]['question_id'],
        );
    }

    public function goToQuestion(int $index): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        if (isset($this->answerStates[$index])) {
            $this->currentIndex = $index;
            $this->selectedOptionId = $this->answerStates[$index]['selected_option_id'] ?? null;
        }
    }

    public function next(): void
    {
        if (! $this->ensureWithinDeadline() || ! $this->persistCurrentAnswer()) {
            return;
        }

        if ($this->currentIndex < count($this->answerStates) - 1) {
            $this->currentIndex++;
            $this->selectedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
        }
    }

    public function previous(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        if ($this->currentIndex > 0) {
            $this->currentIndex--;
            $this->selectedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
        }
    }

    public function submitExam(): void
    {
        // Past the deadline this closes the attempt like a timeout instead.
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        // Capture the on-screen pick, so an answer chosen on the last question
        // still counts when "Selesai Ujian" is clicked without "Simpan Jawaban".
        if (! $this->persistCurrentAnswer()) {
            return;
        }

        $attempt = app(SkbExamService::class)->submitAttempt($this->resolveAttempt());
        $this->redirect(route('peserta.mode-ujian.skb-result', $attempt), navigate: false);
    }

    protected function freshDeadlineAttempt(): ?Model
    {
        return SkbExamAttempt::query()->where('user_id', Auth::id())->find($this->attemptId, ['id', 'status', 'expires_at']);
    }

    protected function closeTimedOutAttempt(): string
    {
        // Same capture as a manual submit, but only for a pick made before
        // time ran out (the browser locks the screen at zero). A stale pick is
        // just skipped: time is up, the attempt closes anyway.
        if ($this->withinAnswerGrace()) {
            $this->persistCurrentAnswer(reloadIfStale: false);
        }

        $attempt = app(SkbExamService::class)->submitAttempt($this->resolveAttempt());

        return route('peserta.mode-ujian.skb-result', $attempt);
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        return (bool) $this->currentQuestion?->options->contains('id', $optionId);
    }

    protected function submittedResultUrl(): string
    {
        return route('peserta.mode-ujian.skb-result', $this->attemptId);
    }

    protected function examRoomUrl(): string
    {
        return route('peserta.mode-ujian.skb.room');
    }

    private function resolveAttempt(): SkbExamAttempt
    {
        return SkbExamAttempt::query()->where('user_id', Auth::id())->findOrFail($this->attemptId);
    }

    public function render()
    {
        // Actions may have moved to another question after the current one was
        // read (e.g. to validate the pick): show the question now on screen.
        unset($this->currentQuestion);

        $this->savedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
        $this->unansweredSaved = $this->unansweredCount;

        return view('livewire.peserta.mode-ujian.skb-exam-room');
    }
}
