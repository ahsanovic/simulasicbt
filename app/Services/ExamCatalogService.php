<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Support\Collection;

class ExamCatalogService
{
    /**
     * @return Collection<int, Exam>
     */
    public function availableFullSimulationsFor(int $userId): Collection
    {
        $exams = Exam::query()
            ->where('status', 'published')
            ->whereNull('pin')
            ->withCount('questions')
            ->latest()
            ->get()
            ->reject(fn (Exam $exam) => $exam->isDuel() || $exam->isDrill())
            ->values();

        $attemptStats = ExamAttempt::query()
            ->full()
            ->where('user_id', $userId)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->groupBy('exam_id');

        return $exams->map(function (Exam $exam) use ($attemptStats) {
            /** @var Collection<int, ExamAttempt> $attempts */
            $attempts = $attemptStats->get($exam->id, collect());
            $inProgress = $attempts->first(fn ($attempt) => $attempt->status === ExamAttemptStatus::InProgress && $attempt->isActive());
            $completed = $attempts->where('status', ExamAttemptStatus::Submitted);

            $exam->setAttribute('in_progress_attempt', $inProgress);
            $exam->setAttribute('attempt_count', $completed->count());
            $exam->setAttribute('best_score', $completed->max('total_score') !== null ? (int) $completed->max('total_score') : null);
            $last = $completed->sortByDesc('submitted_at')->first();
            $exam->setAttribute('last_score', $last?->total_score !== null ? (int) $last->total_score : null);

            return $exam;
        });
    }
}
