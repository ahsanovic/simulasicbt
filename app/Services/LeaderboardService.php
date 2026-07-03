<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    private const int LIMIT = 10;

    /**
     * @return array{
     *     entries: Collection<int, array{rank: int, user_id: int, name: string, score: int, is_current: bool}>,
     *     current_user: ?array{rank: int, user_id: int, name: string, score: int, is_current: bool}
     * }
     */
    public function getLiveLeaderboard(int $userId): array
    {
        $topTen = $this->bestScoresQuery()
            ->limit(self::LIMIT)
            ->get();

        $entries = $topTen->values()->map(fn ($row, int $index) => [
            'rank' => $index + 1,
            'user_id' => (int) $row->id,
            'name' => $row->name,
            'score' => (int) $row->best_score,
            'is_current' => (int) $row->id === $userId,
        ]);

        $currentUser = null;

        if (! $entries->contains(fn (array $entry) => $entry['is_current'])) {
            $currentUser = $this->getCurrentUserEntry($userId);
        }

        return [
            'entries' => $entries,
            'current_user' => $currentUser,
        ];
    }

    /** @return ?array{rank: int, user_id: int, name: string, score: int, is_current: bool} */
    private function getCurrentUserEntry(int $userId): ?array
    {
        $bestScore = DB::table('exam_attempts')
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('total_score')
            ->max('total_score');

        if ($bestScore === null) {
            return null;
        }

        $bestScore = (int) $bestScore;

        $rank = DB::query()
            ->fromSub($this->bestScoresQuery(), 'ranked')
            ->where('best_score', '>', $bestScore)
            ->count() + 1;

        $user = DB::table('users')->where('id', $userId)->value('name');

        if ($user === null) {
            return null;
        }

        return [
            'rank' => $rank,
            'user_id' => $userId,
            'name' => $user,
            'score' => $bestScore,
            'is_current' => true,
        ];
    }

    private function bestScoresQuery()
    {
        return DB::table('exam_attempts')
            ->join('users', 'users.id', '=', 'exam_attempts.user_id')
            ->select('users.id', 'users.name', DB::raw('MAX(exam_attempts.total_score) as best_score'))
            ->where('users.role', UserRole::Peserta->value)
            ->where('exam_attempts.status', ExamAttemptStatus::Submitted->value)
            ->whereNotNull('exam_attempts.total_score')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('best_score')
            ->orderBy('users.name');
    }
}
