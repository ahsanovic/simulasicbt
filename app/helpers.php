<?php

use App\Enums\DevotionBadge;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;

if (! function_exists('sanitize_testimonial_text')) {
    function sanitize_testimonial_text(?string $text, bool $multiline = false): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        if ($multiline) {
            $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
            $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        } else {
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        }

        return trim($text);
    }
}

if (! function_exists('storage_asset')) {
    function storage_asset(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('plain_text_for_tts')) {
    function plain_text_for_tts(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

if (! function_exists('html_for_display')) {
    function html_for_display(?string $html): ?string
    {
        return app(HtmlSanitizer::class)->resolveForDisplay($html);
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

if (! function_exists('format_ai_recommendation')) {
    function format_ai_recommendation(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/`(.+?)`/s', '$1', $text) ?? $text;

        return trim($text);
    }
}

if (! function_exists('format_psychology_report')) {
    function format_psychology_report(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text) ?? $text;
        $text = strip_tags($text, '<b><strong>');
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text) ?? $text;

        return trim($text);
    }
}

if (! function_exists('format_cheat_sheet_content')) {
    function format_cheat_sheet_content(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $html = Str::markdown($markdown);

        return app(HtmlSanitizer::class)->sanitize($html) ?? '';
    }
}

if (! function_exists('format_question_duration')) {
    function format_question_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
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

if (! function_exists('devotion_badge_for_xp')) {
    /** @return array{value: string, label: string, description: string, classes: string} */
    function devotion_badge_for_xp(int $xp): array
    {
        return DevotionBadge::fromXp($xp)->toArray();
    }
}
