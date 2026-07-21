<?php

namespace App\Services;

use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Models\DuelSession;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class PesertaStatisticsService
{
    private const int SCORE_TREND_LIMIT = 20;

    public function __construct(
        private readonly ExamWeaknessAnalysisService $weaknessAnalysis,
        private readonly GamificationService $gamification,
        private readonly LeaderboardSummaryService $leaderboardSummary,
        private readonly FormationMatchmakingService $formationMatchmaking,
        private readonly FlashcardService $flashcardService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $userId = (int) $user->id;
        $officialAttempts = $this->officialSubmittedAttempts($userId);
        $hasHistory = $officialAttempts->isNotEmpty();
        $totalXp = $this->gamification->totalXp($user);
        $passingGrades = exam_passing_grades();
        $scoreMax = exam_score_max();

        $bestScores = $this->bestScores($officialAttempts);
        $overview = $this->overview($officialAttempts, $userId, $totalXp, $user);
        $weaknessStats = $hasHistory ? $this->weaknessAnalysis->getStatsForUser($userId) : null;

        return [
            'has_history' => $hasHistory,
            'overview' => $overview,
            'best_scores' => $bestScores,
            'passing_grades' => $passingGrades,
            'score_max' => $scoreMax,
            'score_trend' => $this->scoreTrend($officialAttempts, $passingGrades),
            'pillar_comparison' => $this->pillarComparison($bestScores, $passingGrades, $scoreMax),
            'weakness_stats' => $weaknessStats,
            'time_management' => $weaknessStats['time_management'] ?? ['has_data' => false],
            'activity' => $this->activityBreakdown($userId),
            'gamification' => [
                'total_xp' => $totalXp,
                'devotion' => $this->gamification->devotionProgress($totalXp),
                'streak' => $this->gamification->dailyStreakInfo($user),
                'remedial_unlock' => $this->gamification->remedialUnlockProgress($totalXp),
            ],
            'flashcards' => [
                'active' => $this->flashcardService->activeCount($user),
                'due' => $this->flashcardService->dueCount($user),
            ],
            'leaderboard_ranks' => $this->leaderboardSummary->getRanks($userId),
            'formation_summary' => $hasHistory
                ? $this->formationMatchmaking->getDashboardSummary($user)
                : null,
            'duel' => $this->duelStats($userId),
            'recent_attempts' => $this->recentAttempts($userId),
        ];
    }

    /**
     * @return Collection<int, ExamAttempt>
     */
    private function officialSubmittedAttempts(int $userId): Collection
    {
        return ExamAttempt::query()
            ->official()
            ->where('user_id', $userId)
            ->whereNull('duel_session_id')
            ->whereNull('event_id')
            ->where('status', ExamAttemptStatus::Submitted)
            ->whereNotNull('total_score')
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * @param  Collection<int, ExamAttempt>  $officialAttempts
     * @return array{total_simulations: int, average_total: int, best_total: ?int, pass_count: int, pass_rate: int, improvement: ?int}
     */
    private function overview(Collection $officialAttempts, int $userId, int $totalXp, User $user): array
    {
        $total = $officialAttempts->count();
        $passed = $officialAttempts->filter(fn (ExamAttempt $attempt) => exam_attempt_passes(
            $attempt->score_twk,
            $attempt->score_tiu,
            $attempt->score_tkp,
            $attempt->total_score,
        ))->count();

        $firstScore = $officialAttempts->first()?->total_score;
        $lastScore = $officialAttempts->last()?->total_score;
        $improvement = ($firstScore !== null && $lastScore !== null && $total >= 2)
            ? $lastScore - $firstScore
            : null;

        return [
            'total_simulations' => $total,
            'average_total' => $total > 0 ? (int) round((float) $officialAttempts->avg('total_score')) : 0,
            'best_total' => $total > 0 ? (int) $officialAttempts->max('total_score') : null,
            'pass_count' => $passed,
            'pass_rate' => $total > 0 ? (int) round(($passed / $total) * 100) : 0,
            'improvement' => $improvement,
            'total_xp' => $totalXp,
            'streak_days' => $this->gamification->dailyStreakInfo($user)['streak'] ?? 0,
        ];
    }

    /**
     * @param  Collection<int, ExamAttempt>  $officialAttempts
     * @return array{twk: ?int, tiu: ?int, tkp: ?int, total: ?int}
     */
    private function bestScores(Collection $officialAttempts): array
    {
        if ($officialAttempts->isEmpty()) {
            return ['twk' => null, 'tiu' => null, 'tkp' => null, 'total' => null];
        }

        return [
            'twk' => (int) $officialAttempts->max('score_twk'),
            'tiu' => (int) $officialAttempts->max('score_tiu'),
            'tkp' => (int) $officialAttempts->max('score_tkp'),
            'total' => (int) $officialAttempts->max('total_score'),
        ];
    }

    /**
     * @param  Collection<int, ExamAttempt>  $officialAttempts
     * @return array{labels: array<int, string>, totals: array<int, int>, twk: array<int, int>, tiu: array<int, int>, tkp: array<int, int>, passing_total: int}
     */
    /**
     * @param  array{twk: int, tiu: int, tkp: int, total: int}  $passingGrades
     */
    private function scoreTrend(Collection $officialAttempts, array $passingGrades): array
    {
        $points = $officialAttempts
            ->take(-self::SCORE_TREND_LIMIT)
            ->values()
            ->map(function (ExamAttempt $attempt, int $index) {
                $date = $attempt->submitted_at?->timezone(config('app.timezone'))->format('d M');

                return [
                    'label' => '#'.($index + 1).($date ? ' · '.$date : ''),
                    'total' => (int) $attempt->total_score,
                    'twk' => (int) $attempt->score_twk,
                    'tiu' => (int) $attempt->score_tiu,
                    'tkp' => (int) $attempt->score_tkp,
                    'passed' => exam_attempt_passes(
                        $attempt->score_twk,
                        $attempt->score_tiu,
                        $attempt->score_tkp,
                        $attempt->total_score,
                    ),
                ];
            });

        return [
            'labels' => $points->pluck('label')->all(),
            'totals' => $points->pluck('total')->all(),
            'twk' => $points->pluck('twk')->all(),
            'tiu' => $points->pluck('tiu')->all(),
            'tkp' => $points->pluck('tkp')->all(),
            'passed_flags' => $points->pluck('passed')->all(),
            'passing_total' => $passingGrades['total'],
            'passing_twk' => $passingGrades['twk'],
            'passing_tiu' => $passingGrades['tiu'],
            'passing_tkp' => $passingGrades['tkp'],
        ];
    }

    /**
     * @param  array{twk: ?int, tiu: ?int, tkp: ?int, total: ?int}  $bestScores
     * @param  array{twk: int, tiu: int, tkp: int, total: int}  $passingGrades
     * @param  array{twk: int, tiu: int, tkp: int, total: int}  $scoreMax
     * @return array<int, array{code: string, label: string, best: ?int, passing: int, max: int, percent_of_max: int, meets_passing: bool}>
     */
    private function pillarComparison(array $bestScores, array $passingGrades, array $scoreMax): array
    {
        return collect([
            ['code' => 'twk', 'label' => 'TWK'],
            ['code' => 'tiu', 'label' => 'TIU'],
            ['code' => 'tkp', 'label' => 'TKP'],
            ['code' => 'total', 'label' => 'Total'],
        ])->map(function (array $pillar) use ($bestScores, $passingGrades, $scoreMax) {
            $code = $pillar['code'];
            $best = $bestScores[$code];
            $max = $scoreMax[$code];
            $passing = $passingGrades[$code];

            return [
                'code' => $code,
                'label' => $pillar['label'],
                'best' => $best,
                'passing' => $passing,
                'max' => $max,
                'percent_of_max' => $best !== null && $max > 0 ? (int) round(($best / $max) * 100) : 0,
                'meets_passing' => $best !== null && $best >= $passing,
            ];
        })->values()->all();
    }

    /**
     * @return array{full: int, drill: int, remedial: int, duel: int}
     */
    private function activityBreakdown(int $userId): array
    {
        $base = ExamAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired]);

        return [
            'full' => (clone $base)->where('attempt_type', ExamAttemptType::Full)->whereNull('duel_session_id')->count(),
            'drill' => (clone $base)->where('attempt_type', ExamAttemptType::Drill)->count(),
            'remedial' => (clone $base)->where('attempt_type', ExamAttemptType::Remedial)->count(),
            'duel' => (clone $base)->whereNotNull('duel_session_id')->count(),
        ];
    }

    /**
     * @return array{played: int, wins: int, win_rate: int}
     */
    private function duelStats(int $userId): array
    {
        $played = DuelSession::query()
            ->where('status', DuelSessionStatus::Completed)
            ->where(function ($query) use ($userId) {
                $query->where('host_user_id', $userId)
                    ->orWhere(function ($nested) use ($userId) {
                        $nested->where('opponent_user_id', $userId)
                            ->where('is_bot_opponent', false);
                    });
            })
            ->count();

        $wins = DuelSession::query()
            ->where('status', DuelSessionStatus::Completed)
            ->where('winner_user_id', $userId)
            ->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'win_rate' => $played > 0 ? (int) round(($wins / $played) * 100) : 0,
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, type: string, total: ?int, passed: bool, submitted_at: ?string}>
     */
    private function recentAttempts(int $userId): array
    {
        return ExamAttempt::query()
            ->with('exam:id,title')
            ->where('user_id', $userId)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->whereNull('event_id')
            ->latest('submitted_at')
            ->limit(6)
            ->get()
            ->map(function (ExamAttempt $attempt) {
                $passed = $attempt->total_score !== null && exam_attempt_passes(
                    $attempt->score_twk,
                    $attempt->score_tiu,
                    $attempt->score_tkp,
                    $attempt->total_score,
                );

                return [
                    'id' => $attempt->id,
                    'title' => $attempt->displayTitle(),
                    'type' => $attempt->attempt_type->label(),
                    'total' => $attempt->total_score,
                    'passed' => $passed,
                    'submitted_at' => $attempt->submitted_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i'),
                ];
            })
            ->all();
    }
}
