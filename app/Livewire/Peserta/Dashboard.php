<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Concerns\InteractsWithFullExamStart;
use App\Models\ExamAttempt;
use App\Services\CoinService;
use App\Services\ExamCatalogService;
use App\Services\FlashcardService;
use App\Services\FormationMatchmakingService;
use App\Services\GamificationService;
use App\Services\LeaderboardSummaryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'dashboard', 'showNav' => true])]
#[Title('Dashboard Peserta')]
class Dashboard extends Component
{
    use InteractsWithFullExamStart;

    public function render(
        CoinService $coinService,
        FlashcardService $flashcardService,
        GamificationService $gamificationService,
        LeaderboardSummaryService $leaderboardSummary,
        FormationMatchmakingService $formationMatchmaking,
        ExamCatalogService $examCatalog,
    ) {
        $user = auth()->user();
        $userId = (int) $user->id;

        $hasHistory = ExamAttempt::query()
            ->full()
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted)
            ->exists();

        $totalXp = $gamificationService->totalXp($user);
        $coinBalance = $coinService->balance($user);
        $devotionProgress = $gamificationService->devotionProgress($totalXp);
        $dailyStreakInfo = $gamificationService->dailyStreakInfo($user);
        $flashcardDueCount = $flashcardService->dueCount($user);
        $leaderboardRanks = $leaderboardSummary->getRanks($userId);
        $formationSummary = $hasHistory
            ? $formationMatchmaking->getDashboardSummary($user)
            : null;

        return view('livewire.peserta.dashboard', [
            'exams' => $examCatalog->availableFullSimulationsFor($userId),
            'hasHistory' => $hasHistory,
            'totalXp' => $totalXp,
            'coinBalance' => $coinBalance,
            'devotionProgress' => $devotionProgress,
            'dailyStreakInfo' => $dailyStreakInfo,
            'flashcardDueCount' => $flashcardDueCount,
            'leaderboardRanks' => $leaderboardRanks,
            'formationSummary' => $formationSummary,
        ]);
    }
}
