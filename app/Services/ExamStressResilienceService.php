<?php

namespace App\Services;

use App\Models\ExamAttempt;

class ExamStressResilienceService
{
    public const STRESS_WINDOW_SECONDS = 900;

    public const RED_ZONE_SECONDS = 600;

    public const MIN_ANSWERED_FOR_ANALYSIS = 5;

    public const MIN_STRESS_WINDOW_ANSWERED = 3;

    /**
     * @param  array{red_zone_triggers?: int, red_zone_questions?: list<int>}  $telemetry
     * @return array{
     *     has_data: bool,
     *     insufficient: bool,
     *     reason: ?string,
     *     score: int,
     *     level: string,
     *     level_label: string,
     *     baseline_accuracy: float,
     *     stress_accuracy: float,
     *     accuracy_drop: float,
     *     baseline_answered: int,
     *     stress_answered: int,
     *     total_answered: int,
     *     red_zone_triggers: int,
     *     red_zone_questions: list<int>,
     *     insight: string,
     *     segments: list<array{label: string, accuracy: float, answered: int}>
     * }
     */
    public function analyzeAttempt(ExamAttempt $attempt, array $telemetry = []): array
    {
        if (! $attempt->stress_test_enabled) {
            return $this->emptyAnalysis();
        }

        $attempt->loadMissing(['answers.question', 'answers.selectedOption', 'exam']);

        $totalDurationSeconds = max(1, (int) $attempt->exam?->duration_minutes * 60);
        $durations = $attempt->question_duration['by_sort_order'] ?? [];

        if ($attempt->started_at === null) {
            return $this->emptyAnalysis();
        }

        $baselineCorrect = 0;
        $baselineAnswered = 0;
        $stressCorrect = 0;
        $stressAnswered = 0;
        $totalAnswered = 0;

        foreach ($attempt->answers as $answer) {
            if (! $answer->selected_option_id) {
                continue;
            }

            $sortOrder = (string) $answer->sort_order;
            $timeSpent = max(0, (int) ($durations[$sortOrder] ?? 0));

            if ($timeSpent === 0) {
                continue;
            }

            $totalAnswered++;
            $elapsedSeconds = $this->estimateElapsedSeconds($attempt, $durations, (int) $answer->sort_order);
            $remainingSeconds = max(0, $totalDurationSeconds - $elapsedSeconds);

            $isPositive = $answer->reviewOutcome()->isPositive();

            if ($remainingSeconds <= self::STRESS_WINDOW_SECONDS) {
                $stressAnswered++;
                if ($isPositive) {
                    $stressCorrect++;
                }
            } else {
                $baselineAnswered++;
                if ($isPositive) {
                    $baselineCorrect++;
                }
            }
        }

        $stats = [
            'baseline_answered' => $baselineAnswered,
            'stress_answered' => $stressAnswered,
            'total_answered' => $totalAnswered,
            'red_zone_triggers' => (int) ($telemetry['red_zone_triggers'] ?? 0),
            'red_zone_questions' => array_values(array_map('intval', $telemetry['red_zone_questions'] ?? [])),
        ];

        if ($totalAnswered === 0) {
            return $this->emptyAnalysis();
        }

        $remainingAtSubmit = $this->remainingSecondsAtSubmit($attempt);

        if ($remainingAtSubmit > self::STRESS_WINDOW_SECONDS) {
            return $this->insufficientAnalysis('early_exit', $stats);
        }

        if ($totalAnswered < self::MIN_ANSWERED_FOR_ANALYSIS) {
            return $this->insufficientAnalysis('too_few_answers', $stats);
        }

        if ($stressAnswered < self::MIN_STRESS_WINDOW_ANSWERED) {
            return $this->insufficientAnalysis('too_few_stress_answers', $stats);
        }

        $baselineAccuracy = $baselineAnswered > 0
            ? round(($baselineCorrect / $baselineAnswered) * 100, 1)
            : 0.0;

        $stressAccuracy = round(($stressCorrect / $stressAnswered) * 100, 1);

        $accuracyDrop = $baselineAnswered > 0
            ? max(0, round($baselineAccuracy - $stressAccuracy, 1))
            : 0.0;

        $score = $this->calculateScore($accuracyDrop, $stats['red_zone_triggers'], $baselineAnswered > 0);
        $level = $this->resolveLevel($score);

        return [
            'has_data' => true,
            'insufficient' => false,
            'reason' => null,
            'score' => $score,
            'level' => $level,
            'level_label' => $this->levelLabel($level),
            'baseline_accuracy' => $baselineAccuracy,
            'stress_accuracy' => $stressAccuracy,
            'accuracy_drop' => $accuracyDrop,
            'baseline_answered' => $baselineAnswered,
            'stress_answered' => $stressAnswered,
            'total_answered' => $totalAnswered,
            'red_zone_triggers' => $stats['red_zone_triggers'],
            'red_zone_questions' => $stats['red_zone_questions'],
            'insight' => $this->buildInsight($score, $level, $accuracyDrop, $stressAccuracy, $baselineAnswered > 0),
            'segments' => [
                [
                    'label' => 'Sebelum zona stres',
                    'accuracy' => $baselineAccuracy,
                    'answered' => $baselineAnswered,
                ],
                [
                    'label' => '15 menit terakhir',
                    'accuracy' => $stressAccuracy,
                    'answered' => $stressAnswered,
                ],
            ],
        ];
    }

    private function remainingSecondsAtSubmit(ExamAttempt $attempt): int
    {
        if ($attempt->submitted_at === null || $attempt->expires_at === null) {
            return 0;
        }

        if ($attempt->submitted_at->gte($attempt->expires_at)) {
            return 0;
        }

        return (int) $attempt->submitted_at->diffInSeconds($attempt->expires_at);
    }

    /**
     * @param  array<string, int>  $durations
     */
    private function estimateElapsedSeconds(ExamAttempt $attempt, array $durations, int $targetSortOrder): int
    {
        $elapsed = 0;

        foreach ($attempt->answers->sortBy('sort_order') as $answer) {
            $sortOrder = (int) $answer->sort_order;
            $elapsed += max(0, (int) ($durations[(string) $sortOrder] ?? 0));

            if ($sortOrder === $targetSortOrder) {
                break;
            }
        }

        return $elapsed;
    }

    private function calculateScore(float $accuracyDrop, int $redZoneTriggers, bool $hasBaseline): int
    {
        if (! $hasBaseline) {
            $score = 100 - min(40, $redZoneTriggers * 3);

            return (int) max(0, min(100, round($score)));
        }

        $score = 100 - ($accuracyDrop * 2.5) - min(15, $redZoneTriggers * 2);

        return (int) max(0, min(100, round($score)));
    }

    private function resolveLevel(int $score): string
    {
        if ($score >= 75) {
            return 'tinggi';
        }

        if ($score >= 50) {
            return 'sedang';
        }

        return 'rendah';
    }

    private function levelLabel(string $level): string
    {
        return match ($level) {
            'tinggi' => 'Tinggi',
            'sedang' => 'Sedang',
            default => 'Rendah',
        };
    }

    private function buildInsight(int $score, string $level, float $accuracyDrop, float $stressAccuracy, bool $hasBaseline): string
    {
        if (! $hasBaseline) {
            return "Analisis fokus pada performa di 15 menit terakhir (akurasi {$stressAccuracy}%). Kerjakan lebih banyak soal di fase awal agar perbandingan penurunan akurasi bisa diukur.";
        }

        if ($level === 'tinggi') {
            return "Kamu tetap tenang di 15 menit terakhir! Akurasi jawabanmu di bawah tekanan waktu hanya turun {$accuracyDrop}%, artinya mental bertandingmu sudah sangat siap untuk hari-H.";
        }

        if ($level === 'sedang') {
            return "Ketahanan stresmu cukup baik (skor {$score}%). Akurasi turun {$accuracyDrop}% di fase akhir — latih manajemen waktu agar tidak terburu-buru di menit-menit kritis.";
        }

        return "Akurasi turun signifikan ({$accuracyDrop}%) saat indikator stres aktif. Coba aktifkan mode ini lagi dan latih fokus di 15 menit terakhir — targetkan akurasi zona stres di atas {$stressAccuracy}%.";
    }

    /**
     * @param  array{baseline_answered?: int, stress_answered?: int, total_answered?: int, red_zone_triggers?: int, red_zone_questions?: list<int>}  $stats
     * @return array<string, mixed>
     */
    private function insufficientAnalysis(string $reason, array $stats = []): array
    {
        $messages = [
            'early_exit' => 'Ujian diakhiri sebelum memasuki zona stres (15 menit terakhir). Selesaikan simulasi hingga fase akhir agar ketahanan stres bisa diukur secara valid.',
            'too_few_answers' => 'Terlalu sedikit soal dijawab untuk analisis yang andal. Minimal '.self::MIN_ANSWERED_FOR_ANALYSIS.' soal dengan durasi tercatat diperlukan.',
            'too_few_stress_answers' => 'Belum cukup jawaban di 15 menit terakhir (minimal '.self::MIN_STRESS_WINDOW_ANSWERED.' soal). Coba selesaikan simulasi hingga memasuki fase akhir ujian.',
        ];

        return [
            'has_data' => false,
            'insufficient' => true,
            'reason' => $reason,
            'score' => 0,
            'level' => 'rendah',
            'level_label' => 'Belum Terukur',
            'baseline_accuracy' => 0.0,
            'stress_accuracy' => 0.0,
            'accuracy_drop' => 0.0,
            'baseline_answered' => (int) ($stats['baseline_answered'] ?? 0),
            'stress_answered' => (int) ($stats['stress_answered'] ?? 0),
            'total_answered' => (int) ($stats['total_answered'] ?? 0),
            'red_zone_triggers' => (int) ($stats['red_zone_triggers'] ?? 0),
            'red_zone_questions' => array_values(array_map('intval', $stats['red_zone_questions'] ?? [])),
            'insight' => $messages[$reason] ?? 'Data belum cukup untuk menghitung ketahanan stres.',
            'segments' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalysis(): array
    {
        return [
            'has_data' => false,
            'insufficient' => false,
            'reason' => null,
            'score' => 0,
            'level' => 'rendah',
            'level_label' => 'Rendah',
            'baseline_accuracy' => 0.0,
            'stress_accuracy' => 0.0,
            'accuracy_drop' => 0.0,
            'baseline_answered' => 0,
            'stress_answered' => 0,
            'total_answered' => 0,
            'red_zone_triggers' => 0,
            'red_zone_questions' => [],
            'insight' => '',
            'segments' => [],
        ];
    }
}
