<?php

if (! function_exists('storage_asset')) {
    function storage_asset(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('html_for_display')) {
    function html_for_display(?string $html): ?string
    {
        return app(\App\Services\HtmlSanitizer::class)->resolveForDisplay($html);
    }
}

if (! function_exists('format_exam_score')) {
    function format_exam_score(mixed $score, string $empty = '—'): string
    {
        if ($score === null || $score === '') {
            return $empty;
        }

        return (string) (int) round((float) $score);
    }
}

if (! function_exists('exam_passing_grades')) {
    /** @return array{twk: int, tiu: int, tkp: int, total: int} */
    function exam_passing_grades(): array
    {
        return config('exam.passing_grades');
    }
}

if (! function_exists('exam_score_max')) {
    /** @return array{twk: int, tiu: int, tkp: int, total: int} */
    function exam_score_max(): array
    {
        return config('exam.score_max');
    }
}

if (! function_exists('exam_score_passes')) {
    function exam_score_passes(mixed $score, int $threshold): bool
    {
        if ($score === null || $score === '') {
            return false;
        }

        return (int) round((float) $score) >= $threshold;
    }
}

if (! function_exists('format_exam_remaining_time')) {
    function format_exam_remaining_time(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 menit';
        }

        if ($seconds < 60) {
            return $seconds.' detik';
        }

        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return $minutes.' menit';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? "{$hours} jam {$remainingMinutes} menit"
            : "{$hours} jam";
    }
}

if (! function_exists('exam_attempt_passes')) {
    function exam_attempt_passes(mixed $twk, mixed $tiu, mixed $tkp, mixed $total): bool
    {
        $grades = exam_passing_grades();

        return exam_score_passes($twk, $grades['twk'])
            && exam_score_passes($tiu, $grades['tiu'])
            && exam_score_passes($tkp, $grades['tkp'])
            && exam_score_passes($total, $grades['total']);
    }
}
