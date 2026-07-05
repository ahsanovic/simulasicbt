<?php

namespace App\Services;

use App\Models\AiRecommendation;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekRecommendationService
{
    public function __construct(
        private ExamWeaknessAnalysisService $weaknessAnalysis,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.deepseek.key'));
    }

    public function hasValidRecommendation(int $userId): bool
    {
        $recommendation = AiRecommendation::query()->where('user_id', $userId)->first();

        if (! $recommendation) {
            return false;
        }

        $stats = $this->weaknessAnalysis->getStatsForUser($userId);
        $latestAttemptAt = $stats['latest_attempt_at'] ?? null;

        if ($latestAttemptAt === null) {
            return false;
        }

        return $recommendation->latest_attempt_at?->toDateTimeString() >= $latestAttemptAt;
    }

    public function getStoredRecommendation(int $userId): ?AiRecommendation
    {
        $recommendation = AiRecommendation::query()->where('user_id', $userId)->first();

        if (! $recommendation || ! $this->hasValidRecommendation($userId)) {
            return null;
        }

        return $recommendation;
    }

    public function generateForUser(User $user): AiRecommendation
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API key DeepSeek belum dikonfigurasi. Tambahkan DEEPSEEK_API_KEY di file .env.');
        }

        $stats = $this->weaknessAnalysis->getStatsForUser($user->id);

        if (($stats['total_simulations'] ?? 0) === 0) {
            throw new RuntimeException('Belum ada riwayat simulasi untuk dianalisis.');
        }

        $text = $this->callDeepSeek($user, $stats);

        return AiRecommendation::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'recommendation_text' => $text,
                'weakness_stats' => $stats,
                'latest_attempt_at' => $stats['latest_attempt_at'],
                'generated_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function callDeepSeek(User $user, array $stats): string
    {
        $model = config('services.deepseek.model', 'deepseek-chat');
        $timeout = (int) config('services.deepseek.timeout', 120);

        try {
            $response = Http::withToken(config('services.deepseek.key'))
                ->timeout($timeout)
                ->acceptJson()
                ->post('https://api.deepseek.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.7,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($user, $stats),
                        ],
                    ],
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?? 'Gagal menghubungi DeepSeek. Periksa API key dan koneksi internet.';

            throw new RuntimeException($message, previous: $exception);
        }

        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Respons DeepSeek kosong.');
        }

        return format_ai_recommendation(trim($content));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Anda adalah mentor persiapan CPNS yang ramah, memotivasi, dan praktis. Tugas Anda menganalisis statistik kelemahan peserta simulasi SKD CPNS berdasarkan data yang diberikan.

Aturan output:
- Gunakan bahasa Indonesia yang natural dan menyemangati.
- Sapa peserta dengan namanya.
- Ringkas namun substantif (2-4 paragraf pendek).
- Sebut kekuatan dan kelemahan spesifik berdasarkan data pilar (TWK, TIU, TKP) dan sub-materi.
- Jika tersedia data manajemen waktu, analisis ritme pengerjaan (awal vs akhir ujian) dan berikan saran pacing yang konkret.
- Akhiri dengan baris "Saran Tindakan:" (teks biasa, tanpa tanda bintang) berisi 2-3 poin tindakan konkret yang bisa dilakukan besok atau pada simulasi berikutnya.
- Jangan gunakan format markdown sama sekali (termasuk **tebal**, *miring*, # heading, atau backtick). Gunakan teks biasa saja.
- Boleh gunakan bullet sederhana dengan tanda "- " untuk saran tindakan.
- Jangan mengarang data di luar statistik yang diberikan.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function userPrompt(User $user, array $stats): string
    {
        $pillarLines = collect($stats['pillars'] ?? [])
            ->map(fn (array $pillar, string $code) => sprintf(
                '- %s: %d%% (%s)',
                strtoupper($code),
                $pillar['percentage'],
                $pillar['status_label'],
            ))
            ->implode("\n");

        $materialLines = collect($stats['materials'] ?? [])
            ->take(12)
            ->map(fn (array $material) => sprintf(
                '- %s: %d%% (%s, %d salah dari %d soal)',
                $material['display_name'],
                $material['percentage'],
                $material['status_label'],
                $material['wrong'],
                $material['total'],
            ))
            ->implode("\n");

        $passingGrades = exam_passing_grades();

        $timeManagement = $stats['time_management'] ?? [];
        $timeLines = collect($timeManagement['summary_lines'] ?? [])
            ->map(fn (string $line) => '- '.$line)
            ->implode("\n");

        $timeSection = ($timeManagement['has_data'] ?? false)
            ? <<<TIME

Data manajemen waktu (dari {$timeManagement['total_exams_with_data']} simulasi dengan data durasi):
Batas aman rata-rata per soal: {$timeManagement['safe_seconds_per_question']} detik
{$timeLines}
TIME
            : "\nData manajemen waktu: belum tersedia (simulasi belum merekam durasi per soal).";

        return <<<PROMPT
Nama peserta: {$user->name}
Total simulasi selesai: {$stats['total_simulations']}
Ambang batas CPNS: TWK {$passingGrades['twk']}, TIU {$passingGrades['tiu']}, TKP {$passingGrades['tkp']}, Total {$passingGrades['total']}

Akurasi per pilar:
{$pillarLines}

Detail sub-materi (diurutkan dari yang paling lemah):
{$materialLines}
{$timeSection}

Buat evaluasi personal dan saran belajar berdasarkan data di atas. Sertakan analisis pola manajemen waktu jika data tersedia.
PROMPT;
    }
}
