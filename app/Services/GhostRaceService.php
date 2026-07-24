<?php

namespace App\Services;

use App\DTOs\GhostRaceScore;
use App\DTOs\GhostRaceTrackState;
use App\DTOs\GhostRaceWeeklyRecap;
use App\DTOs\GhostRival;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\GhostRaceTier;
use App\Enums\UserRole;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use App\Notifications\GhostRivalPulledAhead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhostRaceService
{
    public const int CACHE_TTL_SECONDS = 900;

    public const int WEEKLY_XP_CAP = 500;

    public const int RIVAL_NEIGHBOR_RANGE = 3;

    public function __construct(
        private FormationMatchmakingService $formationMatchmaking,
        private GamificationService $gamification,
        private LearningPlanService $learningPlan,
    ) {}

    public function getTrackState(User $user): GhostRaceTrackState
    {
        if (! $this->formationMatchmaking->userHasExamHistory((int) $user->id)) {
            return GhostRaceTrackState::hidden();
        }

        $user = $user->fresh(['formation']);

        return GhostRaceTrackState::fromArray(Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL_SECONDS,
            fn () => $this->buildTrackState($user)->toArray(),
        ));
    }

    public function selectRival(User $user, ?int $rivalUserId): void
    {
        if ($rivalUserId === null) {
            $user->forceFill(['ghost_race_rival_user_id' => null])->save();
            $this->forgetUserCache($user->fresh(['formation']));

            return;
        }

        if (! $this->isSelectableRival($user, $rivalUserId)) {
            throw ValidationException::withMessages([
                'rival' => 'Rival tidak tersedia untuk formasi Anda.',
            ]);
        }

        $user->forceFill(['ghost_race_rival_user_id' => $rivalUserId])->save();
        $this->forgetUserCache($user->fresh(['formation']));
    }

    public function setNotificationsMuted(User $user, bool $muted): void
    {
        $user->forceFill(['ghost_race_notifications_muted' => $muted])->save();
        $this->forgetUserCache($user->fresh(['formation']));
    }

    public function evaluateRivalGapNotification(User $user): ?string
    {
        $user = $user->fresh(['formation']);

        if ($user->ghost_race_notifications_muted) {
            $this->syncLastSeenGapIfChanged($user, $this->currentGapPoints($user));

            return null;
        }

        if (! $this->formationMatchmaking->userHasExamHistory((int) $user->id)) {
            return null;
        }

        if ($this->hasNotifiedToday((int) $user->id)) {
            $this->syncLastSeenGapIfChanged($user, $this->currentGapPoints($user));

            return null;
        }

        $currentGap = $this->currentGapPoints($user);

        if ($currentGap === null) {
            return null;
        }

        $previousGap = $user->ghost_race_last_seen_gap;
        $message = null;

        if ($previousGap !== null
            && $currentGap > $previousGap
            && $currentGap > 0
        ) {
            $state = $this->getTrackState($user);
            $gapIncrease = $currentGap - $previousGap;

            $user->notify(new GhostRivalPulledAhead(
                rivalAlias: $state->ghost->alias,
                gapPoints: $currentGap,
                gapIncrease: $gapIncrease,
            ));

            $this->markNotifiedToday((int) $user->id);

            $message = "{$state->ghost->alias} memperlebar jarak +{$gapIncrease} poin — kini unggul {$currentGap} poin.";
        }

        $this->syncLastSeenGapIfChanged($user, $currentGap);

        return $message;
    }

    public function handleActivityCompleted(User $user): void
    {
        $user = $user->fresh(['formation']);

        $this->forgetUserCache($user);

        $message = $this->evaluateRivalGapNotification($user);

        if ($message !== null) {
            session()->flash('warning', $message);
        }
    }

    public function clearRivalForUser(User $user): void
    {
        $user->forceFill([
            'ghost_race_rival_user_id' => null,
            'ghost_race_last_seen_gap' => null,
        ])->save();

        $this->forgetUserCache($user->fresh(['formation']));
    }

    public function forgetUserCache(User $user): void
    {
        Cache::forget($this->cacheKey($user->fresh(['formation'])));
    }

    public function forgetFormationCaches(int $formationId): void
    {
        $userIds = User::query()
            ->where('formation_id', $formationId)
            ->pluck('id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);

            if ($user !== null) {
                $this->forgetUserCache($user);
            }
        }
    }

    private function buildTrackState(User $user): GhostRaceTrackState
    {
        $tier = $this->resolveTier($user);
        $userScore = $this->raceScoreFor($user);
        $ghost = $this->resolveGhostRival($user, $tier);
        $gapPoints = max(0, $ghost->score->total - $userScore->total);
        $availableRivals = $this->availableRivalsFor($user, $tier);

        $this->ensureWeeklySnapshot($user, $userScore->total, $gapPoints);

        return new GhostRaceTrackState(
            visible: true,
            tier: $tier,
            userPosition: $userScore->total,
            ghostPosition: $ghost->score->total,
            targetPosition: 100,
            gapPoints: $gapPoints,
            ghost: $ghost,
            userScore: $userScore,
            formationName: $user->formation?->name,
            checkpoint: $this->nearestCheckpoint($userScore->total),
            cta: $this->suggestCta($user, $tier, $userScore, $ghost),
            message: $this->buildMessage($user, $tier, $ghost, $userScore, $gapPoints),
            notificationsMuted: (bool) $user->ghost_race_notifications_muted,
            availableRivals: $availableRivals,
            selectedRivalUserId: $user->ghost_race_rival_user_id,
            weeklyRecap: $this->weeklyRecapFor($user, $userScore->total, $gapPoints),
        );
    }

    private function resolveTier(User $user): GhostRaceTier
    {
        if ($user->formation === null) {
            return GhostRaceTier::NoFormation;
        }

        $stats = $this->formationMatchmaking->getFormationStats((int) $user->formation->id);

        if ($stats['applicant_count'] < FormationMatchmakingService::MIN_APPLICANTS_FOR_RANK) {
            return GhostRaceTier::FormationSparse;
        }

        return GhostRaceTier::FormationFull;
    }

    private function raceScoreFor(User $user): GhostRaceScore
    {
        return GhostRaceScore::compute(
            $this->skdComponent($user),
            $this->activityComponent($user),
            $this->readinessComponent($user),
        );
    }

    private function skdComponent(User $user): int
    {
        $best = $this->formationMatchmaking->getUserBestScores((int) $user->id);

        if ($best === null) {
            return 0;
        }

        $maxTotal = exam_score_max()['total'];

        return (int) round(min(100, ($best['total'] / $maxTotal) * 100));
    }

    private function activityComponent(User $user): int
    {
        $weeklyXp = $this->weeklyXpFor((int) $user->id);
        $tasksDone = $this->learningPlan->completedTasksThisWeek($user);
        $drillsDone = $this->drillsCompletedThisWeek((int) $user->id);

        $xpPart = min(70, (int) round(($weeklyXp / self::WEEKLY_XP_CAP) * 70));
        $taskPart = min(30, $tasksDone * 10);
        $drillPart = min(30, $drillsDone * 15);

        return min(100, $xpPart + $taskPart + $drillPart);
    }

    private function readinessComponent(User $user): int
    {
        $summary = $this->formationMatchmaking->getDashboardSummary($user);

        if ($summary && ! $summary['insufficient_data'] && $summary['percentile'] !== null) {
            return (int) round(max(0, min(100, 100 - $summary['percentile'])));
        }

        $xp = $this->gamification->totalXp($user);
        $streak = $this->gamification->dailyStreakInfo($user)['streak'];
        $remedialProgress = $this->gamification->remedialUnlockProgress($xp)['progress_percent'];

        return min(100, (int) round($remedialProgress * 0.6 + min(40, $streak * 5)));
    }

    private function resolveGhostRival(User $user, GhostRaceTier $tier): GhostRival
    {
        return match ($tier) {
            GhostRaceTier::FormationFull => $this->rivalInFormation($user),
            GhostRaceTier::FormationSparse => $this->aggregateGhost($user),
            GhostRaceTier::NoFormation => $this->passingGradeGhost(),
        };
    }

    private function rivalInFormation(User $user): GhostRival
    {
        $rivalId = $user->ghost_race_rival_user_id;

        if ($rivalId !== null && $this->isSelectableRival($user, (int) $rivalId)) {
            return $this->ghostFromUserId((int) $rivalId, $user);
        }

        $topRivalId = $this->findTopRivalUserId((int) $user->id, (int) $user->formation->id);

        if ($topRivalId === null) {
            return $this->aggregateGhost($user);
        }

        return $this->ghostFromUserId($topRivalId, $user);
    }

    private function ghostFromUserId(int $rivalId, User $viewer): GhostRival
    {
        $rival = User::query()->find($rivalId);

        if ($rival === null) {
            return $this->aggregateGhost($viewer);
        }

        $rankedIds = $this->formationRankedUserIds((int) $viewer->formation->id);
        $rank = array_search($rivalId, $rankedIds, true);
        $bestScores = $this->formationMatchmaking->getUserBestScores($rivalId);

        return new GhostRival(
            alias: $this->aliasFor($rivalId),
            score: $this->raceScoreFor($rival),
            lastActivity: $this->lastActivityFor($rivalId),
            bestSkdTotal: $bestScores['total'] ?? null,
            isSynthetic: false,
            rivalUserId: $rivalId,
            rank: $rank !== false ? $rank + 1 : null,
        );
    }

    /**
     * @return list<array{user_id: int, alias: string, rank: int, race_score: int, is_selected: bool}>
     */
    private function availableRivalsFor(User $user, GhostRaceTier $tier): array
    {
        if ($tier !== GhostRaceTier::FormationFull || $user->formation === null) {
            return [];
        }

        $rankedIds = $this->formationRankedUserIds((int) $user->formation->id);
        $userIndex = array_search((int) $user->id, $rankedIds, true);

        if ($userIndex === false) {
            return [];
        }

        $start = max(0, $userIndex - self::RIVAL_NEIGHBOR_RANGE);
        $slice = array_slice($rankedIds, $start, (self::RIVAL_NEIGHBOR_RANGE * 2) + 1);
        $selectedId = $user->ghost_race_rival_user_id ?? $this->findTopRivalUserId((int) $user->id, (int) $user->formation->id);
        $rivals = [];

        foreach ($slice as $rivalId) {
            if ($rivalId === (int) $user->id) {
                continue;
            }

            $rival = User::query()->find($rivalId);

            if ($rival === null) {
                continue;
            }

            $rankIndex = array_search($rivalId, $rankedIds, true);

            $rivals[] = [
                'user_id' => $rivalId,
                'alias' => $this->aliasFor($rivalId),
                'rank' => $rankIndex !== false ? $rankIndex + 1 : 0,
                'race_score' => $this->raceScoreFor($rival)->total,
                'is_selected' => $selectedId === $rivalId,
            ];
        }

        return $rivals;
    }

    private function isSelectableRival(User $user, int $rivalUserId): bool
    {
        if ($user->formation_id === null || $rivalUserId === (int) $user->id) {
            return false;
        }

        return collect($this->availableRivalsFor(
            $user->fresh(['formation']),
            GhostRaceTier::FormationFull,
        ))->contains(fn (array $rival) => $rival['user_id'] === $rivalUserId);
    }

    /** @return list<int> */
    private function formationRankedUserIds(int $formationId): array
    {
        $weeklyXpSubquery = DB::table('xp_rewards')
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as weekly_xp'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('user_id');

        $bestAttempts = DB::table('exam_attempts')
            ->select('user_id', DB::raw('MAX(total_score) as best_total'))
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->groupBy('user_id');

        return DB::table('users')
            ->joinSub($bestAttempts, 'best_attempts', 'best_attempts.user_id', '=', 'users.id')
            ->leftJoinSub($weeklyXpSubquery, 'weekly_xp', 'weekly_xp.user_id', '=', 'users.id')
            ->where('users.role', UserRole::Peserta->value)
            ->where('users.formation_id', $formationId)
            ->orderByDesc('best_attempts.best_total')
            ->orderByDesc('weekly_xp.weekly_xp')
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function aggregateGhost(User $user): GhostRival
    {
        $formation = $user->formation;
        $avgTotal = exam_passing_grades()['total'];

        if ($formation !== null) {
            $stats = $this->formationMatchmaking->getFormationStats((int) $formation->id);
            $avgTotal = (int) round($stats['averages']['total']);
        }

        $maxTotal = exam_score_max()['total'];

        return new GhostRival(
            alias: 'Rata-rata Pelamar',
            score: GhostRaceScore::compute(
                skd: (int) round(min(100, ($avgTotal / $maxTotal) * 100)),
                activity: 55,
                readiness: 60,
            ),
            lastActivity: null,
            bestSkdTotal: $avgTotal,
            isSynthetic: true,
        );
    }

    private function passingGradeGhost(): GhostRival
    {
        $grades = exam_passing_grades();
        $maxTotal = exam_score_max()['total'];

        return new GhostRival(
            alias: 'Standar Kelulusan CPNS',
            score: GhostRaceScore::compute(
                skd: (int) round(min(100, ($grades['total'] / $maxTotal) * 100)),
                activity: 60,
                readiness: 75,
            ),
            lastActivity: null,
            bestSkdTotal: $grades['total'],
            isSynthetic: true,
        );
    }

    private function findTopRivalUserId(int $userId, int $formationId): ?int
    {
        $rankedIds = $this->formationRankedUserIds($formationId);

        foreach ($rankedIds as $rivalId) {
            if ($rivalId !== $userId) {
                return $rivalId;
            }
        }

        return null;
    }

    private function weeklyRecapFor(User $user, int $currentScore, int $currentGap): ?GhostRaceWeeklyRecap
    {
        $weekStart = now()->startOfWeek()->toDateString();

        $snapshot = DB::table('ghost_race_weekly_snapshots')
            ->where('user_id', $user->id)
            ->where('week_start', $weekStart)
            ->first();

        if ($snapshot === null) {
            return null;
        }

        $baselineScore = (int) $snapshot->race_score;
        $baselineGap = (int) $snapshot->gap_points;
        $trackedSinceToday = $snapshot->created_at !== null
            && now()->parse($snapshot->created_at)->isToday();

        return new GhostRaceWeeklyRecap(
            pointsGained: max(0, $currentScore - $baselineScore),
            gapClosed: max(0, $baselineGap - $currentGap),
            currentGap: $currentGap,
            isLeading: $currentGap === 0,
            trackedSinceToday: $trackedSinceToday,
        );
    }

    private function ensureWeeklySnapshot(User $user, int $raceScore, int $gapPoints): void
    {
        $weekStart = now()->startOfWeek()->toDateString();

        DB::table('ghost_race_weekly_snapshots')->insertOrIgnore([
            'user_id' => $user->id,
            'week_start' => $weekStart,
            'race_score' => $raceScore,
            'gap_points' => $gapPoints,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function weeklyXpFor(int $userId): int
    {
        return (int) XpReward::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('amount');
    }

    private function drillsCompletedThisWeek(int $userId): int
    {
        return ExamAttempt::query()
            ->where('user_id', $userId)
            ->where('attempt_type', ExamAttemptType::Drill)
            ->where('status', ExamAttemptStatus::Submitted)
            ->where('submitted_at', '>=', now()->startOfWeek())
            ->count();
    }

    private function aliasFor(int $userId): string
    {
        return 'Pelamar #'.strtoupper(substr(md5((string) $userId), 0, 3));
    }

    private function lastActivityFor(int $userId): ?string
    {
        $latestAttempt = ExamAttempt::query()
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->first();

        if ($latestAttempt === null) {
            return null;
        }

        $label = match (true) {
            $latestAttempt->isDrill() => 'Drill '.($latestAttempt->drillConfig()?->subjectCode->label() ?? 'Soal'),
            $latestAttempt->isFull() => 'Simulasi Full',
            default => 'Latihan',
        };

        return $label.' · '.$latestAttempt->submitted_at->diffForHumans();
    }

    /**
     * @return ?array{label: string, position: int}
     */
    private function nearestCheckpoint(int $position): ?array
    {
        $checkpoints = [
            25 => 'Simulasi Pertama',
            50 => 'Passing Grade',
            75 => 'Siap Kompetitif',
            100 => 'Target Kelulusan',
        ];

        foreach ($checkpoints as $threshold => $label) {
            if ($position < $threshold) {
                return [
                    'label' => $label,
                    'position' => $threshold,
                ];
            }
        }

        return null;
    }

    /**
     * @return ?array{label: string, url: string, reason: string}
     */
    private function suggestCta(User $user, GhostRaceTier $tier, GhostRaceScore $userScore, GhostRival $ghost): ?array
    {
        if ($tier === GhostRaceTier::NoFormation) {
            return [
                'label' => 'Pilih Target Jabatan',
                'url' => route('peserta.simulasi-formasi'),
                'reason' => 'Pilih formasi untuk rival yang lebih relevan',
            ];
        }

        $gaps = [
            'skd' => $ghost->score->skdComponent - $userScore->skdComponent,
            'activity' => $ghost->score->activityComponent - $userScore->activityComponent,
            'readiness' => $ghost->score->readinessComponent - $userScore->readinessComponent,
        ];

        arsort($gaps);
        $largestGap = (string) array_key_first($gaps);

        if ($gaps[$largestGap] <= 0) {
            return null;
        }

        if ($largestGap === 'skd') {
            return [
                'label' => 'Kerjakan Simulasi Full',
                'url' => route('peserta.simulasi.index'),
                'reason' => 'Skor SKD Anda masih di belakang rival',
            ];
        }

        if ($largestGap === 'activity') {
            $improvement = $this->formationMatchmaking->analyzeForUser($user)['improvement'] ?? null;
            $subject = $improvement['subject'] ?? 'TWK';

            return [
                'label' => "Mulai Drill {$subject}",
                'url' => route('peserta.drill.index'),
                'reason' => 'Rival lebih aktif belajar minggu ini',
            ];
        }

        return [
            'label' => 'Lihat Rencana Belajar',
            'url' => route('peserta.rencana-belajar.index'),
            'reason' => 'Tingkatkan kesiapan ujian Anda',
        ];
    }

    private function buildMessage(
        User $user,
        GhostRaceTier $tier,
        GhostRival $ghost,
        GhostRaceScore $userScore,
        int $gapPoints,
    ): string {
        if ($tier === GhostRaceTier::NoFormation) {
            return 'Bandingkan progres Anda dengan standar kelulusan CPNS. Pilih target jabatan untuk rival yang lebih relevan.';
        }

        if ($tier === GhostRaceTier::FormationSparse) {
            $count = $this->formationMatchmaking->getFormationStats((int) $user->formation->id)['applicant_count'];

            return "Data pelamar {$user->formation->name} masih terbatas ({$count} pelamar). Rival ditampilkan sebagai rata-rata formasi.";
        }

        if ($gapPoints <= 0) {
            return 'Anda memimpin di lintasan formasi ini. Pertahankan konsistensi belajar!';
        }

        return "{$ghost->alias} unggul {$gapPoints} poin di lintasan formasi Anda.";
    }

    private function currentGapPoints(User $user): ?int
    {
        $state = $this->getTrackState($user);

        if (! $state->visible) {
            return null;
        }

        return $state->gapPoints;
    }

    private function syncLastSeenGapIfChanged(User $user, ?int $gap): void
    {
        if ($gap === null || $user->ghost_race_last_seen_gap === $gap) {
            return;
        }

        $user->forceFill(['ghost_race_last_seen_gap' => $gap])->save();
    }

    private function hasNotifiedToday(int $userId): bool
    {
        return Cache::has($this->notificationCacheKey($userId));
    }

    private function markNotifiedToday(int $userId): void
    {
        Cache::put($this->notificationCacheKey($userId), true, now()->endOfDay());
    }

    private function notificationCacheKey(int $userId): string
    {
        return 'ghost_race_notif_'.$userId.'_'.now()->toDateString();
    }

    private function cacheKey(User $user): string
    {
        $formationSegment = $user->formation_id ?? 'none';
        $rivalSegment = $user->ghost_race_rival_user_id ?? 'auto';

        return "ghost_race_track_v4_{$user->id}_{$formationSegment}_{$rivalSegment}";
    }
}
