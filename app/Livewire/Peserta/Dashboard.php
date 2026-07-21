<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Concerns\InteractsWithStressTestModal;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\CoinService;
use App\Services\FlashcardService;
use App\Services\FormationMatchmakingService;
use App\Services\GamificationService;
use App\Services\LeaderboardSummaryService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'dashboard', 'showNav' => true])]
#[Title('Dashboard Peserta')]
class Dashboard extends Component
{
    use InteractsWithStressTestModal;

    public ?int $pinExamId = null;

    public string $examPin = '';

    public function startExam(int $examId): void
    {
        $exam = Exam::query()->findOrFail($examId);

        if (! $exam->isAvailable()) {
            session()->flash('error', 'Ujian tidak tersedia saat ini.');

            return;
        }

        $existingAttempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($existingAttempt && $existingAttempt->isActive()) {
            $this->redirect(route('peserta.exam.room', $exam));

            return;
        }

        if ($exam->requiresPin()) {
            $this->pinExamId = $exam->id;
            $this->examPin = '';
            $this->resetErrorBag('examPin');

            return;
        }

        $this->promptStressTestOrBeginExam($exam);
    }

    public function confirmPin(): void
    {
        if ($this->pinExamId === null) {
            return;
        }

        $exam = Exam::query()->findOrFail($this->pinExamId);

        if (! $exam->isAvailable()) {
            $this->closePinModal();
            session()->flash('error', 'Ujian tidak tersedia saat ini.');

            return;
        }

        if (strtoupper(trim($this->examPin)) !== strtoupper((string) $exam->pin)) {
            $this->addError('examPin', 'PIN ujian salah.');

            return;
        }

        $existingAttempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($existingAttempt && $existingAttempt->isActive()) {
            $this->redirect(route('peserta.exam.room', $exam));

            return;
        }

        $this->beginExamAttempt($exam, false);
    }

    public function closePinModal(): void
    {
        $this->pinExamId = null;
        $this->examPin = '';
        $this->resetErrorBag('examPin');
    }

    public function render(CoinService $coinService, FlashcardService $flashcardService, GamificationService $gamificationService, LeaderboardSummaryService $leaderboardSummary, FormationMatchmakingService $formationMatchmaking)
    {
        $exams = Exam::query()
            ->where('status', 'published')
            ->whereNull('pin')
            ->withCount('questions')
            ->latest()
            ->get()
            ->reject(fn (Exam $exam) => $exam->isDuel())
            ->values();

        $attemptStats = ExamAttempt::query()
            ->full()
            ->where('user_id', auth()->id())
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->groupBy('exam_id');

        $hasHistory = ExamAttempt::query()
            ->full()
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::Submitted)
            ->exists();

        $exams = $exams->map(function (Exam $exam) use ($attemptStats) {
            /** @var Collection<int, ExamAttempt> $attempts */
            $attempts = $attemptStats->get($exam->id, collect());
            $inProgress = $attempts->first(fn ($a) => $a->status === ExamAttemptStatus::InProgress && $a->isActive());
            $completed = $attempts->where('status', ExamAttemptStatus::Submitted);

            $exam->setAttribute('in_progress_attempt', $inProgress);
            $exam->setAttribute('attempt_count', $completed->count());
            $exam->setAttribute('best_score', $completed->max('total_score') !== null ? (int) $completed->max('total_score') : null);
            $last = $completed->sortByDesc('submitted_at')->first();
            $exam->setAttribute('last_score', $last?->total_score !== null ? (int) $last->total_score : null);

            return $exam;
        });

        $user = auth()->user();
        $totalXp = $gamificationService->totalXp($user);
        $coinBalance = $coinService->balance($user);
        $devotionProgress = $gamificationService->devotionProgress($totalXp);
        $dailyStreakInfo = $gamificationService->dailyStreakInfo($user);
        $flashcardDueCount = $flashcardService->dueCount($user);
        $leaderboardRanks = $leaderboardSummary->getRanks((int) auth()->id());
        $formationSummary = $hasHistory
            ? $formationMatchmaking->getDashboardSummary($user)
            : null;

        return view('livewire.peserta.dashboard', compact('exams', 'hasHistory', 'totalXp', 'coinBalance', 'devotionProgress', 'dailyStreakInfo', 'flashcardDueCount', 'leaderboardRanks', 'formationSummary'));
    }
}
