<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;

class ExamTimeManagementService
{
    public function safeSecondsPerQuestion(Exam $exam, ?int $totalQuestions = null): int
    {
        $total = max(1, $totalQuestions ?? ExamQuestionGeneratorService::TOTAL_QUESTIONS);

        return (int) round(($exam->duration_minutes * 60) / $total);
    }

    /**
     * @return array<string, int>
     */
    public function durationsForAttempt(ExamAttempt $attempt): array
    {
        $raw = $attempt->question_duration['by_sort_order'] ?? [];

        return collect($raw)
            ->mapWithKeys(fn ($seconds, $key) => [(string) $key => max(0, (int) $seconds)])
            ->all();
    }

    /**
     * @return array{key: string, label: string, color: string}
     */
    public function durationStatus(int $seconds, int $safeSeconds): array
    {
        if ($safeSeconds <= 0) {
            return ['key' => 'normal', 'label' => 'Normal', 'color' => 'slate'];
        }

        if ($seconds > (int) round($safeSeconds * 1.5)) {
            return ['key' => 'too_long', 'label' => 'Terlalu Lama!', 'color' => 'rose'];
        }

        if ($seconds > $safeSeconds) {
            return ['key' => 'slow', 'label' => 'Agak Lambat', 'color' => 'amber'];
        }

        if ($seconds > 0 && $seconds < (int) round($safeSeconds * 0.5)) {
            return ['key' => 'fast', 'label' => 'Cepat', 'color' => 'emerald'];
        }

        return ['key' => 'normal', 'label' => 'Normal', 'color' => 'slate'];
    }

    /**
     * @return array{
     *     has_data: bool,
     *     safe_seconds_per_question: int,
     *     total_tracked_seconds: int,
     *     average_by_pillar: array<string, array{label: string, average_seconds: int, safe_seconds: int, question_count: int}>,
     *     longest_questions: array<int, array{sort_order: int, question_number: int, subject_code: string, subject_label: string, seconds: int, status: array{key: string, label: string, color: string}}>,
     *     by_sort_order: array<string, array{seconds: int, subject_code: string, subject_label: string, status: array{key: string, label: string, color: string}}>
     * }
     */
    public function analyzeAttempt(ExamAttempt $attempt): array
    {
        $attempt->loadMissing(['answers.question.subject', 'exam']);

        $durations = $this->durationsForAttempt($attempt);
        $safeSeconds = $this->safeSecondsPerQuestion($attempt->exam, $attempt->answers->count());
        $answersBySort = $attempt->answers->keyBy(fn ($answer) => (string) $answer->sort_order);

        $pillarTotals = [];
        $pillarCounts = [];
        $enriched = [];

        foreach ($durations as $sortOrder => $seconds) {
            $answer = $answersBySort->get($sortOrder);
            $subjectCode = $answer?->question?->subject?->code->value ?? 'unknown';
            $subjectLabel = $answer?->question?->subject?->code->label() ?? '—';

            $enriched[$sortOrder] = [
                'seconds' => $seconds,
                'subject_code' => $subjectCode,
                'subject_label' => $subjectLabel,
                'status' => $this->durationStatus($seconds, $safeSeconds),
            ];

            if ($subjectCode === 'unknown') {
                continue;
            }

            $pillarTotals[$subjectCode] = ($pillarTotals[$subjectCode] ?? 0) + $seconds;
            $pillarCounts[$subjectCode] = ($pillarCounts[$subjectCode] ?? 0) + 1;
        }

        $averageByPillar = [];

        foreach (['twk', 'tiu', 'tkp'] as $code) {
            $count = $pillarCounts[$code] ?? 0;

            if ($count === 0) {
                continue;
            }

            $averageByPillar[$code] = [
                'label' => strtoupper($code),
                'average_seconds' => (int) round($pillarTotals[$code] / $count),
                'safe_seconds' => $safeSeconds,
                'question_count' => $count,
            ];
        }

        $longestQuestions = collect($enriched)
            ->map(fn (array $item, string $sortOrder) => [
                'sort_order' => (int) $sortOrder,
                'question_number' => (int) $sortOrder,
                'subject_code' => $item['subject_code'],
                'subject_label' => $item['subject_label'],
                'seconds' => $item['seconds'],
                'status' => $item['status'],
            ])
            ->sortByDesc('seconds')
            ->take(5)
            ->values()
            ->all();

        return [
            'has_data' => $durations !== [],
            'safe_seconds_per_question' => $safeSeconds,
            'total_tracked_seconds' => array_sum($durations),
            'average_by_pillar' => $averageByPillar,
            'longest_questions' => $longestQuestions,
            'by_sort_order' => $enriched,
        ];
    }

    /**
     * @return array{
     *     has_data: bool,
     *     total_exams_with_data: int,
     *     average_seconds_by_pillar: array<string, int>,
     *     safe_seconds_per_question: int,
     *     early_phase_average: ?int,
     *     late_phase_average: ?int,
     *     summary_lines: array<int, string>
     * }
     */
    public function analyzeUserTimePatterns(int $userId): array
    {
        $attempts = ExamAttempt::query()
            ->with(['exam', 'answers.question.subject'])
            ->where('user_id', $userId)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->whereNotNull('question_duration')
            ->latest('submitted_at')
            ->get();

        $attemptsWithData = $attempts->filter(fn (ExamAttempt $attempt) => $this->durationsForAttempt($attempt) !== []);

        if ($attemptsWithData->isEmpty()) {
            return [
                'has_data' => false,
                'total_exams_with_data' => 0,
                'average_seconds_by_pillar' => [],
                'safe_seconds_per_question' => $this->safeSecondsPerQuestion(
                    Exam::query()->latest()->first() ?? new Exam(['duration_minutes' => 100]),
                ),
                'early_phase_average' => null,
                'late_phase_average' => null,
                'summary_lines' => [],
            ];
        }

        $pillarTotals = [];
        $pillarCounts = [];
        $earlyTotals = [];
        $earlyCounts = [];
        $lateTotals = [];
        $lateCounts = [];

        foreach ($attemptsWithData as $attempt) {
            $analysis = $this->analyzeAttempt($attempt);
            $totalQuestions = $attempt->answers->count();
            $earlyCutoff = (int) max(1, ceil($totalQuestions * 0.25));
            $lateStart = (int) max(1, floor($totalQuestions * 0.75) + 1);

            foreach ($analysis['by_sort_order'] as $sortOrder => $item) {
                $code = $item['subject_code'];

                if ($code === 'unknown') {
                    continue;
                }

                $pillarTotals[$code] = ($pillarTotals[$code] ?? 0) + $item['seconds'];
                $pillarCounts[$code] = ($pillarCounts[$code] ?? 0) + 1;

                $order = (int) $sortOrder;

                if ($order <= $earlyCutoff) {
                    $earlyTotals[] = $item['seconds'];
                    $earlyCounts[] = 1;
                }

                if ($order >= $lateStart) {
                    $lateTotals[] = $item['seconds'];
                    $lateCounts[] = 1;
                }
            }
        }

        $averageByPillar = [];

        foreach (['twk', 'tiu', 'tkp'] as $code) {
            $count = $pillarCounts[$code] ?? 0;

            if ($count === 0) {
                continue;
            }

            $averageByPillar[$code] = (int) round($pillarTotals[$code] / $count);
        }

        $safeSeconds = $this->safeSecondsPerQuestion($attemptsWithData->first()->exam);

        $earlyAverage = count($earlyTotals) > 0
            ? (int) round(array_sum($earlyTotals) / count($earlyTotals))
            : null;

        $lateAverage = count($lateTotals) > 0
            ? (int) round(array_sum($lateTotals) / count($lateTotals))
            : null;

        $summaryLines = [];

        foreach ($averageByPillar as $code => $average) {
            $comparison = $average > $safeSeconds ? 'lebih lambat' : ($average < $safeSeconds ? 'lebih cepat' : 'sesuai');
            $summaryLines[] = sprintf(
                '%s: rata-rata %d detik/soal (%s batas aman %d detik)',
                strtoupper($code),
                $average,
                $comparison,
                $safeSeconds,
            );
        }

        if ($earlyAverage !== null && $lateAverage !== null && $earlyAverage > 0) {
            $summaryLines[] = sprintf(
                'Ritme awal (25%% soal pertama): %d detik/soal vs akhir (25%% terakhir): %d detik/soal',
                $earlyAverage,
                $lateAverage,
            );
        }

        return [
            'has_data' => true,
            'total_exams_with_data' => $attemptsWithData->count(),
            'average_seconds_by_pillar' => $averageByPillar,
            'safe_seconds_per_question' => $safeSeconds,
            'early_phase_average' => $earlyAverage,
            'late_phase_average' => $lateAverage,
            'summary_lines' => $summaryLines,
        ];
    }
}
