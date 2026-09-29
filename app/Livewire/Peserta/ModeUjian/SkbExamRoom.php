<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Enums\ExamAttemptStatus;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Services\SkbExamService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ujian SKB')]
class SkbExamRoom extends Component
{
    public int $attemptId;

    public int $attemptExpiresAt;

    public int $currentIndex = 0;

    public ?int $selectedOptionId = null;

    /** @var array<int, array{id: int, sort_order: int, question_id: int, selected_option_id: ?int, is_marked: bool}> */
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
        $this->answerStates = $attempt->answers->map(fn ($answer) => [
            'id' => $answer->id,
            'sort_order' => $answer->sort_order,
            'question_id' => $answer->skb_question_id,
            'selected_option_id' => $answer->selected_option_id,
            'is_marked' => (bool) $answer->is_marked,
        ])->all();
        $this->selectedOptionId = $this->answerStates[0]['selected_option_id'] ?? null;
    }

    public function getRemainingSecondsProperty(): int
    {
        return max(0, $this->attemptExpiresAt - now()->timestamp);
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

    public function getProgressPercentProperty(): int
    {
        $total = count($this->answerStates);

        return $total > 0 ? (int) round(($this->answeredCount / $total) * 100) : 0;
    }

    public function getCurrentAnswerProperty()
    {
        $questionId = $this->answerStates[$this->currentIndex]['question_id'] ?? null;

        if ($questionId === null) {
            return null;
        }

        $question = SkbQuestion::query()->with('options')->find($questionId);

        if ($question === null) {
            return null;
        }

        return (object) [
            'question' => $question,
        ];
    }

    public function selectOption(int $optionId): void
    {
        if (! $this->isValidOptionForCurrentQuestion($optionId)) {
            return;
        }

        // Selecting only updates the on-screen highlight. The answer is NOT
        // persisted (and the question is NOT counted as answered) until the
        // peserta explicitly clicks "Simpan & Lanjutkan" / "Simpan Jawaban".
        // Navigating away via the navigator / "Sebelumnya" discards an
        // unsaved pick — the last saved value is restored.
        $this->selectedOptionId = $optionId;
    }

    public function saveAnswer(): void
    {
        $state = $this->answerStates[$this->currentIndex] ?? null;

        if ($state === null) {
            return;
        }

        $optionId = $this->selectedOptionId;

        if ($optionId !== null && ! $this->isValidOptionForCurrentQuestion($optionId)) {
            $optionId = null;
        }

        $saved = app(SkbExamService::class)->saveAnswer(
            $this->resolveAttempt(),
            $state['question_id'],
            $optionId,
        );

        if (! $saved) {
            $this->checkExpiry();

            return;
        }

        $this->answerStates[$this->currentIndex]['selected_option_id'] = $optionId;
    }

    public function toggleMark(): void
    {
        $this->answerStates[$this->currentIndex]['is_marked'] = ! $this->answerStates[$this->currentIndex]['is_marked'];

        app(SkbExamService::class)->toggleMark(
            $this->resolveAttempt(),
            $this->answerStates[$this->currentIndex]['question_id'],
        );
    }

    public function goToQuestion(int $index): void
    {
        if (isset($this->answerStates[$index])) {
            $this->currentIndex = $index;
            $this->selectedOptionId = $this->answerStates[$index]['selected_option_id'] ?? null;
        }
    }

    public function next(): void
    {
        $this->saveAnswer();

        if ($this->currentIndex < count($this->answerStates) - 1) {
            $this->currentIndex++;
            $this->selectedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
        }
    }

    public function previous(): void
    {
        if ($this->currentIndex > 0) {
            $this->currentIndex--;
            $this->selectedOptionId = $this->answerStates[$this->currentIndex]['selected_option_id'] ?? null;
        }
    }

    public function submitExam(): void
    {
        $attempt = app(SkbExamService::class)->submitAttempt($this->resolveAttempt());
        $this->redirect(route('peserta.mode-ujian.skb-result', $attempt), navigate: false);
    }

    public function checkExpiry(): void
    {
        if ($this->remainingSeconds <= 0) {
            $attempt = app(SkbExamService::class)->submitAttempt($this->resolveAttempt());
            session()->flash('error', 'Waktu ujian SKB habis. Jawaban otomatis dikumpulkan.');
            $this->redirect(route('peserta.mode-ujian.skb-result', $attempt), navigate: false);
        }
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        $questionId = $this->answerStates[$this->currentIndex]['question_id'] ?? null;

        if ($questionId === null) {
            return false;
        }

        return SkbQuestionOption::query()
            ->whereKey($optionId)
            ->where('skb_question_id', $questionId)
            ->exists();
    }

    private function resolveAttempt(): SkbExamAttempt
    {
        return SkbExamAttempt::query()->findOrFail($this->attemptId);
    }

    public function render()
    {
        return view('livewire.peserta.mode-ujian.skb-exam-room');
    }
}
