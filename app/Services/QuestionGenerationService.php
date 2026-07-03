<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QuestionGenerationService
{
    public function __construct(
        private GeneratedQuestionValidator $validator,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(Subject $subject, Material $material, string $difficulty, int $count): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API key OpenAI belum dikonfigurasi. Tambahkan OPENAI_API_KEY di file .env.');
        }

        $payload = $this->callOpenAi($subject, $material, $difficulty, $count);
        $rawQuestions = $this->extractQuestions($payload);

        if ($rawQuestions === []) {
            throw new RuntimeException('OpenAI tidak mengembalikan soal. Coba lagi.');
        }

        return array_map(
            fn (array $question) => $this->normalizeQuestion($question, $subject->code, $difficulty),
            array_slice($rawQuestions, 0, $count),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateOne(Subject $subject, Material $material, string $difficulty): array
    {
        return $this->generate($subject, $material, $difficulty, 1)[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function callOpenAi(Subject $subject, Material $material, string $difficulty, int $count): array
    {
        $model = config('services.openai.model', 'gpt-4o');
        $timeout = (int) config('services.openai.timeout', 120);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout($timeout)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($subject->code),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($subject, $material, $difficulty, $count),
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

        return $this->decodeJsonContent($content);
    }

    private function systemPrompt(SubjectCode $subjectCode): string
    {
        $base = <<<'PROMPT'
Anda adalah ahli pembuat soal seleksi CPNS berbahasa Indonesia. Buat soal berkualitas, relevan dengan materi, dan sesuai format ujian resmi.

Aturan output:
- Balas HANYA dengan JSON valid (tanpa markdown).
- Setiap soal WAJIB memiliki pembahasan/penjelasan jawaban yang jelas dan edukatif.
- Gunakan teks biasa (bukan HTML) untuk content, explanation, dan opsi.
- Buat tepat 5 opsi jawaban dengan label A, B, C, D, E.
PROMPT;

        if ($subjectCode === SubjectCode::Tkp) {
            return $base."\n\nUntuk TKP:\n- Semua opsi is_correct harus false.\n- Setiap opsi WAJIB memiliki score_weight unik dari 1 sampai 5 (5 = respons paling ideal, 1 = paling tidak ideal).\n- Acak penempatan bobot skor di opsi A–E; jangan selalu menempatkan bobot 5 di opsi A.\n- Variasikan opsi mana yang mendapat bobot tertinggi/terendah di setiap soal.";
        }

        return $base."\n\nUntuk TWK/TIU:\n- Tepat SATU opsi harus is_correct: true, sisanya false.\n- score_weight harus null untuk semua opsi.\n- Variasikan posisi jawaban benar; jangan selalu menempatkan is_correct: true pada opsi A.\n- Sebar jawaban benar di A, B, C, D, atau E secara bergantian antar soal.";
    }

    private function userPrompt(Subject $subject, Material $material, string $difficulty, int $count): string
    {
        $subjectLabel = $subject->code->label();
        $materialName = $material->displayName();
        $difficultyLabel = match ($difficulty) {
            'easy' => 'Mudah',
            'hard' => 'Sulit',
            default => 'Sedang',
        };

        $schema = $subject->code === SubjectCode::Tkp
            ? '{"questions":[{"content":"...","explanation":"...","options":[{"label":"A","content":"...","is_correct":false,"score_weight":2},{"label":"B","content":"...","is_correct":false,"score_weight":5},...]}]}'
            : '{"questions":[{"content":"...","explanation":"...","options":[{"label":"A","content":"...","is_correct":false,"score_weight":null},{"label":"C","content":"...","is_correct":true,"score_weight":null},...]}]}';

        $varietyHint = $subject->code === SubjectCode::Tkp
            ? '- Pastikan bobot 1–5 tersebar acak di opsi A–E, tidak berpola sama tiap soal.'
            : '- Pastikan jawaban benar tersebar di posisi A–E yang berbeda-beda antar soal.';

        return <<<PROMPT
Buat {$count} soal {$subjectLabel} dengan spesifikasi berikut:
- Materi: {$materialName}
- Tingkat kesulitan: {$difficultyLabel}
- Konteks: Simulasi ujian CPNS
{$varietyHint}

Format JSON:
{$schema}
PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractQuestions(array $payload): array
    {
        $questions = $payload['questions'] ?? null;

        if (! is_array($questions)) {
            return [];
        }

        return array_values(array_filter($questions, fn ($question) => is_array($question)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonContent(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Respons OpenAI bukan JSON valid.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function normalizeQuestion(array $question, SubjectCode $subjectCode, string $difficulty): array
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $rawOptions = is_array($question['options'] ?? null) ? $question['options'] : [];
        $options = [];
        $correctOptionIndex = 0;

        foreach ($labels as $index => $label) {
            $rawOption = $this->findOptionByLabel($rawOptions, $label) ?? ($rawOptions[$index] ?? []);
            $isCorrect = (bool) ($rawOption['is_correct'] ?? false);

            if ($isCorrect) {
                $correctOptionIndex = $index;
            }

            $options[] = [
                'label' => $label,
                'content_type' => 'text',
                'content' => trim((string) ($rawOption['content'] ?? '')),
                'image_path' => null,
                'is_correct' => $isCorrect,
                'score_weight' => isset($rawOption['score_weight']) ? (int) $rawOption['score_weight'] : null,
            ];
        }

        if ($subjectCode === SubjectCode::Tkp) {
            $options = $this->ensureTkpWeights($options);
        } else {
            $hasCorrect = collect($options)->contains(fn ($option) => $option['is_correct']);

            if (! $hasCorrect) {
                $correctOptionIndex = random_int(0, count($options) - 1);
                $options[$correctOptionIndex]['is_correct'] = true;
            }
        }

        [$options, $correctOptionIndex] = $this->shuffleOptionOrder($options, $subjectCode, $correctOptionIndex);

        $normalized = [
            'content' => trim((string) ($question['content'] ?? '')),
            'explanation' => trim((string) ($question['explanation'] ?? '')),
            'difficulty' => $difficulty,
            'options' => $options,
            'correct_option_index' => $correctOptionIndex,
        ];

        $normalized['validation_error'] = $this->validator->validate($normalized, $subjectCode);

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, mixed>>
     */
    private function ensureTkpWeights(array $options): array
    {
        $weights = array_map(
            fn (array $option) => isset($option['score_weight']) ? (int) $option['score_weight'] : null,
            $options,
        );

        $presentWeights = array_values(array_filter($weights, fn (?int $weight) => $weight !== null));
        sort($presentWeights);

        $hasValidWeights = count($presentWeights) === 5
            && $presentWeights === [1, 2, 3, 4, 5]
            && count($presentWeights) === count(array_unique($presentWeights));

        if (! $hasValidWeights) {
            $randomWeights = [1, 2, 3, 4, 5];
            shuffle($randomWeights);

            foreach ($options as $index => $option) {
                $options[$index]['score_weight'] = $randomWeights[$index];
            }
        }

        foreach ($options as $index => $option) {
            $options[$index]['is_correct'] = false;
        }

        return $options;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function shuffleOptionOrder(array $options, SubjectCode $subjectCode, int $correctOptionIndex): array
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $units = array_map(fn (array $option) => [
            'content_type' => $option['content_type'],
            'content' => $option['content'],
            'image_path' => $option['image_path'],
            'is_correct' => $option['is_correct'],
            'score_weight' => $option['score_weight'],
        ], $options);

        shuffle($units);

        $shuffledOptions = [];
        $newCorrectOptionIndex = 0;

        foreach ($labels as $index => $label) {
            $unit = $units[$index];
            $isCorrect = $subjectCode !== SubjectCode::Tkp && (bool) ($unit['is_correct'] ?? false);

            if ($isCorrect) {
                $newCorrectOptionIndex = $index;
            }

            $shuffledOptions[] = [
                'label' => $label,
                'content_type' => $unit['content_type'],
                'content' => $unit['content'],
                'image_path' => $unit['image_path'],
                'is_correct' => $isCorrect,
                'score_weight' => $unit['score_weight'],
            ];
        }

        if ($subjectCode !== SubjectCode::Tkp) {
            foreach ($shuffledOptions as $index => $option) {
                $shuffledOptions[$index]['is_correct'] = $index === $newCorrectOptionIndex;
            }
        }

        return [$shuffledOptions, $newCorrectOptionIndex];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>|null
     */
    private function findOptionByLabel(array $options, string $label): ?array
    {
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (strtoupper(trim((string) ($option['label'] ?? ''))) === $label) {
                return $option;
            }
        }

        return null;
    }
}
