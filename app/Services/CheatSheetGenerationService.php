<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\MaterialCheatSheet;
use App\Models\Question;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CheatSheetGenerationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function generateForMaterial(Material $material, bool $force = false): MaterialCheatSheet
    {
        $material->loadMissing(['subject', 'materialGroup']);

        if (! $this->isConfigured()) {
            throw new RuntimeException('API key OpenAI belum dikonfigurasi. Tambahkan OPENAI_API_KEY di file .env.');
        }

        $cheatSheet = MaterialCheatSheet::query()->firstOrCreate(
            ['material_id' => $material->id],
            ['status' => MaterialCheatSheet::STATUS_PENDING],
        );

        if ($cheatSheet->isPublished() && ! $force) {
            return $cheatSheet;
        }

        $cheatSheet->update(['status' => MaterialCheatSheet::STATUS_PROCESSING]);

        try {
            $markdown = $this->callOpenAi($material);

            $cheatSheet->update([
                'content' => $markdown,
                'status' => MaterialCheatSheet::STATUS_COMPLETED,
                'generated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $cheatSheet->update([
                'status' => MaterialCheatSheet::STATUS_FAILED,
                'generated_at' => now(),
            ]);

            throw $exception;
        }

        return $cheatSheet->fresh();
    }

    public function buildPrompt(Material $material): string
    {
        $material->loadMissing(['subject', 'materialGroup']);

        return implode("\n\n", [
            $this->systemPrompt($material->subject->code),
            $this->userPrompt($material),
        ]);
    }

    private function callOpenAi(Material $material): string
    {
        $model = config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 120);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout($timeout)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.6,
                    'max_tokens' => 2500,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($material->subject->code),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($material),
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

        return $this->normalizeMarkdown($content);
    }

    private function systemPrompt(SubjectCode $subjectCode): string
    {
        $base = <<<'PROMPT'
Anda adalah tutor ahli kelulusan CPNS nasional (SKD/BKN). Tugas Anda membuat Cheat-Sheet Kilat: ringkasan super padat yang bisa dibaca tuntas kurang dari 2 menit.

Aturan penulisan:
- Gunakan format Markdown murni (##, ###, **bold**, bullet -).
- Tanpa basa-basi: jangan ada kalimat pembuka/penutup seperti "Tentu, ini materinya".
- Langsung mulai ke materi.
- Bahasa Indonesia yang jelas, padat, dan praktis.
- Fokus pada konsep yang sering muncul di CAT CPNS.

Struktur konten WAJIB (gunakan heading persis seperti ini):
## Konsep Inti
## Poin Kunci
## Pola Jebakan Soal
## Contoh Soal & Pembahasan Kilat (pilihan jawaban A, B, C, D, E)
PROMPT;

        if ($subjectCode === SubjectCode::Tkp) {
            return $base."\n\nKhusus TKP:\n- Ingat soal TKP tidak punya satu jawaban benar absolut; gunakan gradasi bobot 1–5.\n- Contoh soal harus menunjukkan cara memilih respons paling ideal (bobot 5) dan mengapa opsi lain kurang tepat.\n- Tekankan etika pelayanan publik, profesionalisme, dan solusi jangka panjang.";
        }

        return $base."\n\nKhusus TWK/TIU:\n- Contoh soal harus punya satu jawaban benar yang jelas.\n- Pembahasan kilat harus menjelaskan logika/rumus/dasar hukum singkat.\n- Soroti distractor yang sering mengecoh peserta.";
    }

    private function userPrompt(Material $material): string
    {
        $subjectLabel = $material->subject->code->label();
        $materialName = $material->displayName();
        $sampleQuestions = $this->sampleQuestionsContext($material);

        $sampleBlock = $sampleQuestions !== ''
            ? "\n\nReferensi gaya soal dari bank soal internal (jangan menyalin mentah, cukup selaraskan gaya):\n{$sampleQuestions}"
            : '';

        return <<<PROMPT
Buat Cheat-Sheet Kilat untuk:
- Kategori: {$subjectLabel}
- Sub-materi: {$materialName}

Pastikan materi spesifik untuk sub-materi di atas, bukan pengantar umum.
{$sampleBlock}
PROMPT;
    }

    private function sampleQuestionsContext(Material $material): string
    {
        $questions = Question::query()
            ->where('material_id', $material->id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(2)
            ->get(['content', 'explanation']);

        if ($questions->isEmpty()) {
            return '';
        }

        return $questions
            ->map(function (Question $question, int $index): string {
                $content = plain_text_for_tts($question->content);
                $explanation = plain_text_for_tts($question->explanation ?? '');

                return ($index + 1).". Soal: {$content}\n   Pembahasan: {$explanation}";
            })
            ->implode("\n");
    }

    private function normalizeMarkdown(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:markdown)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        return trim($content);
    }
}
