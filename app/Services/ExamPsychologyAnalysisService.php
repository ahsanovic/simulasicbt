<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamTelemetry;

class ExamPsychologyAnalysisService
{
    /**
     * @return array{
     *     has_data: bool,
     *     panic_window_minutes: int,
     *     total_changes_in_panic_window: int,
     *     correct_to_wrong_in_panic_window: int,
     *     fast_skim_in_panic_window: int,
     *     average_seconds_in_panic_window: float,
     *     panic_window_question_count: int,
     *     summary_lines: array<int, string>
     * }
     */
    public function aggregateForAttempt(ExamAttempt $attempt): array
    {
        $telemetries = $attempt->telemetries()->orderBy('question_number')->get();

        if ($telemetries->isEmpty()) {
            return $this->emptyAggregate();
        }

        $panicWindow = ExamTelemetry::PANIC_WINDOW_SECONDS;
        $fastSkim = ExamTelemetry::FAST_SKIM_SECONDS;

        $inPanicWindow = $telemetries->filter(
            fn (ExamTelemetry $telemetry) => $telemetry->remaining_time_seconds <= $panicWindow
        );

        $changesInPanic = $telemetries->where('is_changed_at_last_minute', true)->count();
        $correctToWrong = $telemetries->where('changed_from_correct_to_wrong', true)->count();
        $fastSkimCount = $inPanicWindow
            ->filter(fn (ExamTelemetry $telemetry) => $telemetry->time_spent_seconds > 0
                && $telemetry->time_spent_seconds < $fastSkim)
            ->count();

        $avgSeconds = $inPanicWindow->isNotEmpty()
            ? round($inPanicWindow->avg('time_spent_seconds'), 1)
            : 0.0;

        $summaryLines = [];

        if ($changesInPanic > 0) {
            $summaryLines[] = "Mengubah {$changesInPanic} jawaban di ".($panicWindow / 60).' menit terakhir ujian.';
        }

        if ($correctToWrong > 0) {
            $summaryLines[] = "{$correctToWrong} soal awalnya benar/optimal lalu diubah menjadi salah/suboptimal saat waktu menipis.";
        }

        if ($fastSkimCount > 0) {
            $summaryLines[] = "{$fastSkimCount} soal di fase akhir dikerjakan kurang dari {$fastSkim} detik (kemungkinan skimming).";
        }

        if ($avgSeconds > 0 && $inPanicWindow->isNotEmpty()) {
            $summaryLines[] = 'Rata-rata waktu per soal di fase akhir: '.$avgSeconds.' detik.';
        }

        return [
            'has_data' => $summaryLines !== [] || $inPanicWindow->isNotEmpty(),
            'panic_window_minutes' => (int) ($panicWindow / 60),
            'total_changes_in_panic_window' => $changesInPanic,
            'correct_to_wrong_in_panic_window' => $correctToWrong,
            'fast_skim_in_panic_window' => $fastSkimCount,
            'average_seconds_in_panic_window' => $avgSeconds,
            'panic_window_question_count' => $inPanicWindow->count(),
            'summary_lines' => $summaryLines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAggregate(): array
    {
        return [
            'has_data' => false,
            'panic_window_minutes' => (int) (ExamTelemetry::PANIC_WINDOW_SECONDS / 60),
            'total_changes_in_panic_window' => 0,
            'correct_to_wrong_in_panic_window' => 0,
            'fast_skim_in_panic_window' => 0,
            'average_seconds_in_panic_window' => 0.0,
            'panic_window_question_count' => 0,
            'summary_lines' => [],
        ];
    }
}
