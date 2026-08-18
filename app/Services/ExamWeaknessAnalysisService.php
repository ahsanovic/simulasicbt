<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\SkdTarget;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExamWeaknessAnalysisService
{
    private const string CACHE_PREFIX = 'exam_weakness_stats:';

    public function cacheKey(int $userId, ?SkdTarget $skdTarget = null): string
    {
        return self::CACHE_PREFIX.$userId.':'.($skdTarget?->value ?? 'all');
    }

    public function forget(int $userId): void
    {
        foreach (['all', SkdTarget::Cpns->value, SkdTarget::SekolahKedinasan->value] as $suffix) {
            Cache::forget(self::CACHE_PREFIX.$userId.':'.$suffix);
        }
    }

    /**
     * @return array{
     *     total_simulations: int,
     *     pillars: array<string, array{label: string, percentage: int, status: string, status_label: string}>,
     *     materials: array<int, array{material_id: int, subject_code: string, subject_label: string, name: string, display_name: string, percentage: int, status: string, status_label: string, total: int, wrong: int}>,
     *     latest_attempt_at: ?string,
     *     time_management: array<string, mixed>
     * }
     */
    public function getStatsForUser(int $userId, ?SkdTarget $skdTarget = null): array
    {
        return Cache::remember(
            $this->cacheKey($userId, $skdTarget),
            now()->addDay(),
            fn () => $this->buildStats($userId, $skdTarget),
        );
    }

    /**
     * @return array{
     *     total_simulations: int,
     *     pillars: array<string, array{label: string, percentage: int, status: string, status_label: string}>,
     *     materials: array<int, array{material_id: int, subject_code: string, subject_label: string, name: string, display_name: string, percentage: int, status: string, status_label: string, total: int, wrong: int}>,
     *     latest_attempt_at: ?string,
     *     time_management: array<string, mixed>
     * }
     */
    public function buildStats(int $userId, ?SkdTarget $skdTarget = null): array
    {
        $totalSimulations = $this->attemptQuery($userId, $skdTarget)
            ->where('status', ExamAttemptStatus::Submitted)
            ->count();

        $latestAttemptAt = $this->attemptQuery($userId, $skdTarget)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->max('submitted_at');

        if ($totalSimulations === 0) {
            return [
                'total_simulations' => 0,
                'pillars' => [],
                'materials' => [],
                'latest_attempt_at' => null,
                'time_management' => app(ExamTimeManagementService::class)->analyzeUserTimePatterns($userId, $skdTarget),
            ];
        }

        $answers = ExamAnswer::query()
            ->whereHas('attempt', fn (Builder $query) => $this->applyAttemptScope($query, $userId, $skdTarget)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired]))
            ->with([
                'question.subject',
                'question.material.materialGroup',
                'selectedOption',
            ])
            ->get();

        $pillarTotals = [];
        $pillarWrong = [];
        $materialStats = [];

        foreach ($answers as $answer) {
            $question = $answer->question;

            if (! $question?->subject || ! $question->material) {
                continue;
            }

            $subjectCode = $question->subject->code->value;
            $materialId = $question->material_id;
            $isWrong = ! $answer->reviewOutcome()->isPositive();

            $pillarTotals[$subjectCode] = ($pillarTotals[$subjectCode] ?? 0) + 1;

            if ($isWrong) {
                $pillarWrong[$subjectCode] = ($pillarWrong[$subjectCode] ?? 0) + 1;
            }

            if (! isset($materialStats[$materialId])) {
                $material = $question->material;
                $materialStats[$materialId] = [
                    'subject_code' => $subjectCode,
                    'subject_label' => $question->subject->code->label(),
                    'name' => $material->name,
                    'display_name' => $question->subject->code->label().' - '.$material->displayName(),
                    'total' => 0,
                    'wrong' => 0,
                ];
            }

            $materialStats[$materialId]['total']++;
            if ($isWrong) {
                $materialStats[$materialId]['wrong']++;
            }
        }

        $pillars = [];

        foreach (['twk', 'tiu', 'tkp'] as $code) {
            $total = $pillarTotals[$code] ?? 0;

            if ($total === 0) {
                continue;
            }

            $wrong = $pillarWrong[$code] ?? 0;
            $percentage = (int) round((($total - $wrong) / $total) * 100);
            $status = $this->resolveStatus($percentage);

            $pillars[$code] = [
                'label' => strtoupper($code),
                'percentage' => $percentage,
                'status' => $status['key'],
                'status_label' => $status['label'],
            ];
        }

        $materials = collect($materialStats)
            ->map(function (array $item, int $materialId) {
                $percentage = $item['total'] > 0
                    ? (int) round((($item['total'] - $item['wrong']) / $item['total']) * 100)
                    : 0;
                $status = $this->resolveStatus($percentage);

                return [
                    'material_id' => $materialId,
                    'subject_code' => $item['subject_code'],
                    'subject_label' => $item['subject_label'],
                    'name' => $item['name'],
                    'display_name' => $item['display_name'],
                    'percentage' => $percentage,
                    'status' => $status['key'],
                    'status_label' => $status['label'],
                    'total' => $item['total'],
                    'wrong' => $item['wrong'],
                ];
            })
            ->sortBy([
                ['subject_code', 'asc'],
                ['percentage', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'total_simulations' => $totalSimulations,
            'pillars' => $pillars,
            'materials' => $materials,
            'latest_attempt_at' => $latestAttemptAt
                ? Carbon::parse($latestAttemptAt)->toDateTimeString()
                : null,
            'time_management' => app(ExamTimeManagementService::class)->analyzeUserTimePatterns($userId, $skdTarget),
        ];
    }

    private function attemptQuery(int $userId, ?SkdTarget $skdTarget = null): Builder
    {
        return $this->applyAttemptScope(ExamAttempt::query(), $userId, $skdTarget);
    }

    private function applyAttemptScope(Builder $query, int $userId, ?SkdTarget $skdTarget = null): Builder
    {
        $query->where('user_id', $userId);

        if ($skdTarget !== null) {
            $query->where('skd_target', $skdTarget->value);
        }

        return $query;
    }

    /** @return array{key: string, label: string} */
    public function resolveStatus(int $percentage): array
    {
        if ($percentage >= 80) {
            return ['key' => 'aman', 'label' => 'Siap!'];
        }

        if ($percentage >= 60) {
            return ['key' => 'cukup', 'label' => 'Cukup'];
        }

        return ['key' => 'kritis', 'label' => 'Butuh Perhatian Khusus'];
    }
}
