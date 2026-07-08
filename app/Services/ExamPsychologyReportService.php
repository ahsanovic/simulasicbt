<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExamPsychologyReportService
{
    public function __construct(
        private ExamPsychologyAnalysisService $analysis,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function generateForAttempt(ExamAttempt $attempt): void
    {
        $attempt->loadMissing(['user', 'telemetries']);

        if (! $this->isConfigured()) {
            $attempt->update([
                'psychology_report_status' => 'skipped',
                'psychology_report_generated_at' => now(),
            ]);

            return;
        }

        $stats = $this->analysis->aggregateForAttempt($attempt);

        if (! $stats['has_data']) {
            $attempt->update([
                'psychology_report_status' => 'skipped',
                'psychology_report_generated_at' => now(),
            ]);

            return;
        }

        $attempt->update(['psychology_report_status' => 'processing']);

        try {
            $text = $this->callOpenAi($attempt->user, $stats);

            $attempt->update([
                'psychology_report' => $text,
                'psychology_report_status' => 'completed',
                'psychology_report_generated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $attempt->update([
                'psychology_report_status' => 'failed',
                'psychology_report_generated_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function callOpenAi(User $user, array $stats): string
    {
        $model = config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 120);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout($timeout)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
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
                ?? 'Gagal menghubungi OpenAI. Periksa API key dan koneksi internet.';

            throw new RuntimeException($message, previous: $exception);
        }

        $content = data_get($response, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Respons OpenAI kosong.');
        }

        return format_psychology_report(trim($content));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Anda adalah seorang psikolog edutech berpengalaman khusus ujian CAT ASN. Analisis data telemetri perilaku ujian peserta berikut. Berikan kesimpulan mentalitas ujiannya dan berikan 2 saran taktis konkret agar dia tidak panik di menit-menit akhir. Gunakan gaya bahasa mentor yang ramah.

Aturan output:
- Gunakan bahasa Indonesia yang natural dan menyemangati.
- Sapa peserta dengan namanya di awal.
- Ringkas namun substantif (2-3 paragraf pendek).
- Gunakan tag HTML <b> untuk poin penting (bukan markdown).
- Akhiri dengan baris "💡 Saran Mentor AI:" diikuti 1-2 kalimat saran praktis.
- Jangan mengarang data di luar statistik yang diberikan.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function userPrompt(User $user, array $stats): string
    {
        $lines = collect($stats['summary_lines'] ?? [])
            ->map(fn (string $line) => '- '.$line)
            ->implode("\n");

        return <<<PROMPT
Nama peserta: {$user->name}

Data telemetri perilaku ujian (fokus {$stats['panic_window_minutes']} menit terakhir):
- Total perubahan jawaban di fase panik: {$stats['total_changes_in_panic_window']}
- Jawaban benar diubah jadi salah: {$stats['correct_to_wrong_in_panic_window']}
- Soal dikerjakan terlalu cepat (<10 detik) di fase akhir: {$stats['fast_skim_in_panic_window']}
- Rata-rata detik per soal di fase akhir: {$stats['average_seconds_in_panic_window']}
- Jumlah soal tercatat di fase akhir: {$stats['panic_window_question_count']}

Ringkasan pola:
{$lines}

Buat "Rapor Psikologi Ujian" berdasarkan data di atas.
PROMPT;
    }
}
