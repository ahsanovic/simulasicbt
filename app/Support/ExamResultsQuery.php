<?php

namespace App\Support;

use App\Data\ExamResultsExportFilters;
use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Builder;

final class ExamResultsQuery
{
    public static function filtered(ExamResultsExportFilters $filters): Builder
    {
        $query = ExamAttempt::query()
            ->whereNotNull('submitted_at');

        self::applyFilters($query, $filters);

        return $query;
    }

    public static function applyFilters(Builder $query, ExamResultsExportFilters $filters): void
    {
        if ($filters->search !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($filters) {
                $userQuery->where('name', 'like', "%{$filters->search}%")
                    ->orWhere('email', 'like', "%{$filters->search}%");
            });
        }

        if ($filters->examTypeFilter === 'duel') {
            $query->where(function (Builder $duelQuery) {
                $duelQuery->whereNotNull('duel_session_id')
                    ->orWhereHas('exam', fn (Builder $examQuery) => $examQuery->where('settings->is_duel', true));
            });
        }

        if ($filters->examTypeFilter === 'simulasi') {
            $query->whereNull('duel_session_id')
                ->whereDoesntHave('exam', fn (Builder $examQuery) => $examQuery->where('settings->is_duel', true));
        }

        if ($filters->dateFrom !== '') {
            $query->whereDate('submitted_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== '') {
            $query->whereDate('submitted_at', '<=', $filters->dateTo);
        }
    }

    /** @return array{total: int, passed: int} */
    public static function submittedStats(ExamResultsExportFilters $filters): array
    {
        $baseQuery = ExamAttempt::query()
            ->where('status', ExamAttemptStatus::Submitted);

        self::applyFilters($baseQuery, $filters);

        $grades = exam_passing_grades();

        return [
            'total' => (clone $baseQuery)->count(),
            'passed' => (clone $baseQuery)
                ->where('score_twk', '>=', $grades['twk'])
                ->where('score_tiu', '>=', $grades['tiu'])
                ->where('score_tkp', '>=', $grades['tkp'])
                ->where('total_score', '>=', $grades['total'])
                ->count(),
        ];
    }
}
