<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FormationMatchmakingService
{
    public const int MIN_APPLICANTS_FOR_RANK = 5;

    public const int SAFE_ZONE_PERCENTILE = 5;

    private const int SEARCH_LIMIT = 10;

    private const int CACHE_TTL_SECONDS = 900;

    /**
     * @return Collection<int, Formation>
     */
    public function searchFormations(string $query, int $limit = self::SEARCH_LIMIT): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        return Formation::query()
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', '%'.$query.'%')
                    ->orWhere('group', 'like', '%'.$query.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function userHasExamHistory(int $userId): bool
    {
        return DB::table('exam_attempts')
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->exists();
    }

    /** @return ?array{twk: int, tiu: int, tkp: int, total: int} */
    public function getUserBestScores(int $userId): ?array
    {
        $row = DB::table('exam_attempts')
            ->select('score_twk', 'score_tiu', 'score_tkp', 'total_score')
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->orderByDesc('total_score')
            ->orderByDesc('submitted_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'twk' => (int) $row->score_twk,
            'tiu' => (int) $row->score_tiu,
            'tkp' => (int) $row->score_tkp,
            'total' => (int) $row->total_score,
        ];
    }

    public function assignFormation(User $user, int $formationId): void
    {
        Formation::query()->findOrFail($formationId);

        $previousFormationId = $user->formation_id;

        $user->forceFill([
            'formation_id' => $formationId,
            'formation_selected_at' => now(),
        ])->save();

        $this->forgetFormationCache($formationId);

        $ghostRace = app(GhostRaceService::class);

        if ($previousFormationId !== null && (int) $previousFormationId !== $formationId) {
            $ghostRace->clearRivalForUser($user->fresh(['formation']));
        }

        $ghostRace->forgetUserCache($user->fresh(['formation']));
    }

    public function clearFormation(User $user): void
    {
        $previousFormationId = $user->formation_id;

        $user->forceFill([
            'formation_id' => null,
            'formation_selected_at' => null,
        ])->save();

        if ($previousFormationId !== null) {
            $this->forgetFormationCache((int) $previousFormationId);
        }

        $ghostRace = app(GhostRaceService::class);
        $ghostRace->clearRivalForUser($user->fresh(['formation']));
        $ghostRace->forgetUserCache($user->fresh(['formation']));
    }

    /**
     * @return array{
     *     has_history: bool,
     *     formation: ?Formation,
     *     user_scores: ?array{twk: int, tiu: int, tkp: int, total: int},
     *     passes: bool,
     *     applicant_count: int,
     *     rank: ?int,
     *     percentile: ?float,
     *     zone: ?string,
     *     message: ?string,
     *     averages: ?array{twk: float, tiu: float, tkp: float, total: float},
     *     improvement: ?array{subject: string, points: int},
     *     alternative: ?array{name: string, group: string, average_total: float},
     *     insufficient_data: bool
     * }
     */
    public function analyzeForUser(User $user): array
    {
        $user->loadMissing('formation');

        $base = [
            'has_history' => $this->userHasExamHistory((int) $user->id),
            'formation' => $user->formation,
            'user_scores' => null,
            'passes' => false,
            'applicant_count' => 0,
            'rank' => null,
            'percentile' => null,
            'zone' => null,
            'message' => null,
            'averages' => null,
            'improvement' => null,
            'alternative' => null,
            'insufficient_data' => false,
        ];

        if (! $base['has_history']) {
            $base['message'] = 'Selesaikan minimal satu simulasi untuk melihat perbandingan skor per jabatan.';

            return $base;
        }

        $userScores = $this->getUserBestScores((int) $user->id);
        $base['user_scores'] = $userScores;
        $base['passes'] = $userScores !== null && exam_attempt_passes(
            $userScores['twk'],
            $userScores['tiu'],
            $userScores['tkp'],
            $userScores['total'],
        );

        if ($user->formation === null) {
            $base['message'] = 'Pilih target jabatan untuk melihat posisi kompetitif Anda.';

            return $base;
        }

        $formationId = (int) $user->formation->id;
        $stats = $this->getFormationStats($formationId);
        $base['applicant_count'] = $stats['applicant_count'];
        $base['averages'] = $stats['averages'];

        if ($stats['applicant_count'] < self::MIN_APPLICANTS_FOR_RANK) {
            $base['insufficient_data'] = true;
            $base['message'] = sprintf(
                'Data pelamar jabatan %s masih terbatas (%d pelamar). Cek kembali nanti atau pilih jabatan lain.',
                $user->formation->name,
                $stats['applicant_count'],
            );

            return $base;
        }

        if ($userScores === null) {
            return $base;
        }

        $rank = $this->getUserRankInFormation((int) $user->id, $formationId, $userScores['total']);
        $base['rank'] = $rank;
        $base['percentile'] = round(($rank / $stats['applicant_count']) * 100, 1);
        $base['zone'] = $this->resolveZone($base['passes'], $rank, $stats['applicant_count']);
        $base['message'] = $this->buildZoneMessage(
            $user->formation,
            $userScores,
            $rank,
            $stats['applicant_count'],
            $base['zone'],
            $stats['averages'],
        );
        $base['improvement'] = $base['zone'] === 'caution'
            ? $this->suggestImprovement($userScores, $stats['averages'])
            : null;
        $base['alternative'] = $base['zone'] === 'caution'
            ? $this->findLooserAlternative($user->formation, $userScores['total'])
            : null;

        return $base;
    }

    /** @return array{applicant_count: int, averages: array{twk: float, tiu: float, tkp: float, total: float}} */
    public function getFormationStats(int $formationId): array
    {
        return Cache::remember(
            $this->cacheKey($formationId),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeFormationStats($formationId),
        );
    }

    /** @return ?array{rank: ?int, percentile: ?float, zone: ?string, applicant_count: int, message: ?string} */
    public function getDashboardSummary(User $user): ?array
    {
        $analysis = $this->analyzeForUser($user);

        if ($analysis['formation'] === null || $analysis['user_scores'] === null) {
            return null;
        }

        return [
            'formation_name' => $analysis['formation']->name,
            'rank' => $analysis['rank'],
            'percentile' => $analysis['percentile'],
            'zone' => $analysis['zone'],
            'applicant_count' => $analysis['applicant_count'],
            'message' => $analysis['insufficient_data']
                ? 'Data pelamar masih terbatas'
                : ($analysis['message'] ?? null),
            'insufficient_data' => $analysis['insufficient_data'],
        ];
    }

    /** @return array{applicant_count: int, averages: array{twk: float, tiu: float, tkp: float, total: float}} */
    private function computeFormationStats(int $formationId): array
    {
        $rows = $this->formationBestScoresQuery($formationId)->get();

        if ($rows->isEmpty()) {
            return [
                'applicant_count' => 0,
                'averages' => [
                    'twk' => 0.0,
                    'tiu' => 0.0,
                    'tkp' => 0.0,
                    'total' => 0.0,
                ],
            ];
        }

        $userIds = $rows->pluck('user_id');
        $scoreRows = $this->bestAttemptScoresForUsers($userIds);

        return [
            'applicant_count' => $rows->count(),
            'averages' => [
                'twk' => round($scoreRows->avg('score_twk'), 1),
                'tiu' => round($scoreRows->avg('score_tiu'), 1),
                'tkp' => round($scoreRows->avg('score_tkp'), 1),
                'total' => round($rows->avg('best_total'), 1),
            ],
        ];
    }

    private function formationBestScoresQuery(int $formationId)
    {
        $bestAttempts = DB::table('exam_attempts')
            ->select(
                'user_id',
                DB::raw('MAX(total_score) as best_total'),
            )
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->groupBy('user_id');

        return DB::table('users')
            ->joinSub($bestAttempts, 'best_attempts', 'best_attempts.user_id', '=', 'users.id')
            ->where('users.role', UserRole::Peserta->value)
            ->where('users.formation_id', $formationId)
            ->select(
                'users.id as user_id',
                'best_attempts.best_total',
            );
    }

    private function bestAttemptScoresForUsers(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        $bestAttempts = DB::table('exam_attempts')
            ->select(
                'user_id',
                DB::raw('MAX(total_score) as best_total'),
            )
            ->whereIn('user_id', $userIds)
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->groupBy('user_id');

        return DB::table('exam_attempts')
            ->joinSub($bestAttempts, 'best_attempts', function ($join) {
                $join->on('exam_attempts.user_id', '=', 'best_attempts.user_id')
                    ->on('exam_attempts.total_score', '=', 'best_attempts.best_total');
            })
            ->whereIn('exam_attempts.user_id', $userIds)
            ->where('exam_attempts.status', ExamAttemptStatus::Submitted->value)
            ->select(
                'exam_attempts.user_id',
                'exam_attempts.score_twk',
                'exam_attempts.score_tiu',
                'exam_attempts.score_tkp',
                'exam_attempts.total_score',
            )
            ->get();
    }

    private function getUserRankInFormation(int $userId, int $formationId, int $userBestTotal): int
    {
        $betterCount = $this->formationBestScoresQuery($formationId)
            ->where('users.id', '!=', $userId)
            ->where('best_attempts.best_total', '>', $userBestTotal)
            ->count();

        return $betterCount + 1;
    }

    private function resolveZone(bool $passes, int $rank, int $applicantCount): string
    {
        if (! $passes) {
            return 'risk';
        }

        $safeRankThreshold = (int) ceil($applicantCount * (self::SAFE_ZONE_PERCENTILE / 100));

        return $rank <= max(1, $safeRankThreshold) ? 'safe' : 'caution';
    }

    /**
     * @param  array{twk: int, tiu: int, tkp: int, total: int}  $userScores
     * @param  array{twk: float, tiu: float, tkp: float, total: float}  $averages
     */
    private function buildZoneMessage(
        Formation $formation,
        array $userScores,
        int $rank,
        int $applicantCount,
        string $zone,
        array $averages,
    ): string {
        $formationName = $formation->name;

        return match ($zone) {
            'safe' => sprintf(
                'Skor rata-rata kamu (%d) berada di Top %d%% pelamar jabatan %s di aplikasi ini. Pertahankan!',
                $userScores['total'],
                self::SAFE_ZONE_PERCENTILE,
                $formationName,
            ),
            'caution' => sprintf(
                'Skor kamu (%d) masuk Passing Grade, tapi berada di peringkat %d dari %d pelamar jabatan %s di aplikasi ini.',
                $userScores['total'],
                $rank,
                $applicantCount,
                $formationName,
            ),
            default => sprintf(
                'Skor kamu (%d) belum memenuhi Passing Grade untuk jabatan %s. Rata-rata pelamar: %s poin.',
                $userScores['total'],
                $formationName,
                number_format($averages['total'], 0, ',', '.'),
            ),
        };
    }

    /**
     * @param  array{twk: int, tiu: int, tkp: int, total: int}  $userScores
     * @param  array{twk: float, tiu: float, tkp: float, total: float}  $averages
     * @return ?array{subject: string, points: int}
     */
    private function suggestImprovement(array $userScores, array $averages): ?array
    {
        $gaps = [];

        foreach (['twk' => 'TWK', 'tiu' => 'TIU', 'tkp' => 'TKP'] as $key => $label) {
            $gap = (int) ceil($averages[$key] - $userScores[$key]);

            if ($gap > 0) {
                $gaps[$label] = $gap;
            }
        }

        if ($gaps === []) {
            return null;
        }

        arsort($gaps);
        $subject = (string) array_key_first($gaps);

        return [
            'subject' => $subject,
            'points' => $gaps[$subject],
        ];
    }

    /** @return ?array{name: string, group: string, average_total: float} */
    private function findLooserAlternative(Formation $formation, int $userTotalScore): ?array
    {
        $currentStats = $this->getFormationStats((int) $formation->id);

        $alternative = Formation::query()
            ->where('group', $formation->group)
            ->whereKeyNot($formation->id)
            ->get()
            ->map(function (Formation $candidate) use ($userTotalScore, $currentStats) {
                $stats = $this->getFormationStats((int) $candidate->id);

                if ($stats['applicant_count'] < self::MIN_APPLICANTS_FOR_RANK) {
                    return null;
                }

                if ($stats['averages']['total'] >= $currentStats['averages']['total']) {
                    return null;
                }

                if ($userTotalScore < $stats['averages']['total']) {
                    return null;
                }

                return [
                    'name' => $candidate->name,
                    'group' => $candidate->group,
                    'average_total' => $stats['averages']['total'],
                ];
            })
            ->filter()
            ->sortBy('average_total')
            ->first();

        return $alternative ?: null;
    }

    private function cacheKey(int $formationId): string
    {
        return "formation_matchmaking_stats_{$formationId}";
    }

    private function forgetFormationCache(int $formationId): void
    {
        Cache::forget($this->cacheKey($formationId));
        app(GhostRaceService::class)->forgetFormationCaches($formationId);
    }
}
