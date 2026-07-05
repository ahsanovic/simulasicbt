<?php

namespace App\Livewire\Peserta;

use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\DuelSession;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\DuelService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Duel 1v1')]
class DuelRoom extends Component
{
    #[Locked]
    public DuelSession $session;

    #[Locked]
    public ExamAttempt $attempt;

    #[Locked]
    public int $currentIndex = 0;

    public ?int $selectedOptionId = null;

    public bool $showResult = false;

    public bool $waitingForOpponent = false;

    public function mount(DuelSession $session, DuelService $duelService): void
    {
        if (! $session->isParticipant(auth()->id())) {
            abort(403);
        }

        $this->session = $session->load(['host', 'opponent']);

        if ($this->session->status === DuelSessionStatus::Waiting) {
            $this->redirect(route('peserta.duel.index'), navigate: true);

            return;
        }

        if ($this->session->status === DuelSessionStatus::Completed) {
            $this->session = $this->session->load(['hostAttempt', 'opponentAttempt', 'winner']);
            $this->attempt = $this->session->attemptFor(auth()->id())
                ?? abort(404);
            $this->showResult = true;

            return;
        }

        $duelService->checkExpiry($this->session);
        $this->session = $this->session->fresh(['host', 'opponent']);

        if ($this->session->status === DuelSessionStatus::Completed) {
            $this->session = $this->session->fresh(['host', 'opponent', 'hostAttempt', 'opponentAttempt', 'winner']);
            $this->attempt = $this->session->attemptFor(auth()->id()) ?? abort(404);
            $this->showResult = true;

            return;
        }

        $this->attempt = $duelService->startPlayerAttempt($this->session, auth()->user());

        if ($this->attempt->status !== ExamAttemptStatus::InProgress) {
            $this->session = $this->session->fresh(['hostAttempt', 'opponentAttempt', 'winner', 'host', 'opponent']);

            if ($this->session->status === DuelSessionStatus::Completed) {
                $this->showResult = true;
            } else {
                $this->waitingForOpponent = true;
            }

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

    public function getRemainingSecondsProperty(): int
    {
        return $this->attempt->remainingSeconds();
    }

    public function getOpponentProgressProperty(): int
    {
        return $this->session->fresh()->opponentProgressFor(auth()->id());
    }

    public function getOpponentLabelProperty(): string
    {
        return $this->session->opponentLabelFor(auth()->id());
    }

    public function getOpponentHasStartedProperty(): bool
    {
        $session = $this->session->fresh();

        if (auth()->id() === $session->host_user_id) {
            return $session->opponent_attempt_id !== null;
        }

        return $session->host_attempt_id !== null;
    }

    public function getRemainingDuelSecondsProperty(): int
    {
        if ($this->session->expires_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->session->expires_at, false));
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

        ExamAnswer::query()
            ->whereKey($this->currentAnswer->id)
            ->where('exam_attempt_id', $this->attempt->id)
            ->update([
                'selected_option_id' => $optionId,
                'answered_at' => $optionId ? now() : null,
            ]);

        $this->refreshAttemptData();
    }

    public function goToQuestion(int $index): void
    {
        if ($index < 0 || $index >= $this->answers->count()) {
            return;
        }

        $this->saveAnswer();
        $this->currentIndex = $index;
        $this->loadCurrentAnswer();
        $this->syncProgress();
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
            $this->syncProgress();
        }
    }

    public function submitDuel(DuelService $duelService): void
    {
        $this->saveAnswer();
        $this->session = $duelService->submitPlayerAttempt($this->session, auth()->user(), $this->attempt);
        $this->session->load(['hostAttempt', 'opponentAttempt', 'winner', 'host', 'opponent']);
        $this->attempt = $this->session->attemptFor(auth()->id())->fresh();

        if ($this->session->status === DuelSessionStatus::Completed) {
            $this->showResult = true;
            $this->waitingForOpponent = false;
        } else {
            $this->waitingForOpponent = true;
        }
    }

    public function pollSession(DuelService $duelService): void
    {
        if ($this->waitingForOpponent || $this->showResult) {
            $duelService->tickBotProgress($this->session);
            $this->session = $duelService->checkExpiry($this->session->fresh(['host', 'opponent', 'hostAttempt', 'opponentAttempt', 'winner']));

            if ($this->session->status === DuelSessionStatus::Completed) {
                $this->attempt = $this->session->attemptFor(auth()->id()) ?? $this->attempt;
                $this->showResult = true;
                $this->waitingForOpponent = false;
            }

            return;
        }

        $duelService->tickBotProgress($this->session);
        $this->session = $duelService->checkExpiry($this->session->fresh(['host', 'opponent', 'hostAttempt', 'opponentAttempt']));

        if ($this->session->status === DuelSessionStatus::Completed) {
            $this->attempt = $this->session->attemptFor(auth()->id()) ?? $this->attempt;
            $this->showResult = true;
        }

        if ($this->remainingSeconds <= 0 && $this->attempt->status === ExamAttemptStatus::InProgress) {
            $this->session = $duelService->submitPlayerAttempt($this->session, auth()->user(), $this->attempt);
            $this->session->load(['hostAttempt', 'opponentAttempt', 'winner', 'host', 'opponent']);
            $this->attempt = $this->session->attemptFor(auth()->id())->fresh();
            $this->showResult = $this->session->status === DuelSessionStatus::Completed;
            $this->waitingForOpponent = ! $this->showResult;
        }
    }

    private function syncProgress(): void
    {
        app(DuelService::class)->updateProgress(
            $this->session,
            auth()->user(),
            $this->currentIndex + 1,
        );
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

        unset($this->answers, $this->currentAnswer, $this->answeredCount);
        $this->syncProgress();
    }

    private function loadCurrentAnswer(): void
    {
        unset($this->currentAnswer);
        $this->selectedOptionId = $this->currentAnswer?->selected_option_id;
        $this->syncProgress();
    }

    public function render()
    {
        return view('livewire.peserta.duel-room');
    }
}
