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
    public const MAX_QUESTIONS_PER_GENERATE = 10;

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

        $count = max(1, min(self::MAX_QUESTIONS_PER_GENERATE, $count));

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
Bertindaklah sebagai pakar pembuat soal seleksi CPNS (SKD) resmi dari BKN. Tugas Anda adalah membuat soal simulasi CPNS berkualitas tinggi dengan tingkat kesulitan SEDANG hingga SULIT (berbasis HOTS/Higher Order Thinking Skills).

Silakan buat soal dengan ketentuan sebagai berikut:

Kriteria Kualitas Soal (WAJIB DIPATUHI):
- Tingkat Kesulitan: Sedang hingga Sulit. Jangan banyak pertanyaan hafalan langsung (misal: "Kapan UU ini disahkan?"), boleh tapi jangan sering. Gunakan soal berbasis studi kasus, penalaran logis, atau analisis situasi yang mengecoh.
- Pilihan Ganda: Wajib menyediakan 5 opsi (A, B, C, D, E). 
- Distractor (Pengecoh): Opsi pengecoh harus terlihat sangat logis dan mirip dengan jawaban benar agar menantang bagi peserta.
- Khusus TKP: Semua opsi harus mencerminkan tindakan positif, namun memiliki gradasi poin nilai (1 sampai 5) berdasarkan efektivitas, profesionalisme, dan solusi jangka panjang terbaik. Buat soal TKP yang benar-benar menantang dan memerlukan penalaran yang mendalam.

Kunci Jawaban: [Jawaban yang Benar, atau Pembagian Poin 1-5 jika ini soal TKP]
Pembahasan: [Berikan penjelasan ilmiah, logis, atau dasar hukum/rumus mengapa jawaban tersebut benar dan mengapa opsi lain kurang tepat. Pembahasan harus detail agar peserta paham.]
---

Jika tidak tahu tentang soal-soal TWK hafalan pasal-pasal UU/Peraturan/Undang-Undang, jangan membuat soal yang berkaitan dengan hal tersebut.
Berikan juga soal-soal terkait kondisi global saat ini (tapi jangan sering-sering).

Aturan output:
- Balas HANYA dengan JSON valid (tanpa markdown).
- Setiap soal WAJIB memiliki pembahasan/penjelasan jawaban yang jelas dan edukatif.
- Gunakan teks biasa (bukan HTML) untuk content, explanation, dan opsi.
- Buat tepat 5 opsi jawaban dengan label A, B, C, D, E.
- Untuk TWK/TIU, sertakan field correct_answer berisi salah satu label A/B/C/D/E yang konsisten dengan opsi benar.
PROMPT;

        if ($subjectCode === SubjectCode::Tkp) {
            return $base."\n\nUntuk TKP:\n- Semua opsi is_correct harus false.\n- Setiap opsi WAJIB memiliki score_weight unik dari 1 sampai 5 (5 = respons paling ideal, 1 = paling tidak ideal).\n- Acak penempatan bobot skor di opsi A–E; jangan selalu menempatkan bobot 5 di opsi A.\n- Variasikan opsi mana yang mendapat bobot tertinggi/terendah di setiap soal.";
        }

        return $base."\n\nUntuk TWK/TIU:\n- Tepat SATU opsi harus is_correct: true, sisanya false.\n- Field correct_answer WAJIB ada dan nilainya harus sama persis dengan label opsi yang is_correct: true.\n- score_weight harus null untuk semua opsi.\n- Variasikan posisi jawaban benar; jangan selalu menempatkan is_correct: true pada opsi A.\n- Sebar jawaban benar di A, B, C, D, atau E secara bergantian antar soal.\n- Pembahasan harus konsisten dengan jawaban benar; jangan menyebut label opsi yang berbeda.";
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
            : '{"questions":[{"content":"...","explanation":"...","correct_answer":"C","options":[{"label":"A","content":"...","is_correct":false,"score_weight":null},{"label":"C","content":"...","is_correct":true,"score_weight":null},...]}]}';

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

        foreach ($labels as $index => $label) {
            $rawOption = $this->findOptionByLabel($rawOptions, $label) ?? ($rawOptions[$index] ?? []);

            $options[] = [
                'label' => $label,
                'content_type' => 'text',
                'content' => trim((string) ($rawOption['content'] ?? '')),
                'image_path' => null,
                'is_correct' => false,
                'score_weight' => isset($rawOption['score_weight']) ? (int) $rawOption['score_weight'] : null,
                'marked_correct' => $this->parseIsCorrect($rawOption),
            ];
        }

        if ($subjectCode === SubjectCode::Tkp) {
            $options = $this->ensureTkpWeights($options);
            $correctOptionIndex = 0;
        } else {
            $correctOptionIndex = $this->resolveCorrectOptionIndex($question, $options);

            foreach ($options as $index => $option) {
                $options[$index]['is_correct'] = $index === $correctOptionIndex;
                unset($options[$index]['marked_correct']);
            }
        }

        foreach ($options as $index => $option) {
            unset($options[$index]['marked_correct']);
        }

        // Jangan acak ulang posisi opsi setelah AI mengembalikan label, supaya label pada
        // pembahasan (mis. "jawaban E") tetap konsisten dengan opsi yang ditampilkan.

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
        $newCorrectOptionIndex = $correctOptionIndex;

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
     * @param  array<string, mixed>  $question
     * @param  array<int, array<string, mixed>>  $options
     */
    private function resolveCorrectOptionIndex(array $question, array $options): int
    {
        $answerLabel = strtoupper(trim((string) (
            $question['correct_answer']
            ?? $question['correct_option']
            ?? $question['answer_key']
            ?? ''
        )));

        if (in_array($answerLabel, ['A', 'B', 'C', 'D', 'E'], true)) {
            return (int) array_search($answerLabel, ['A', 'B', 'C', 'D', 'E'], true);
        }

        foreach ($options as $index => $option) {
            if ($option['marked_correct'] ?? false) {
                return $index;
            }
        }

        return random_int(0, max(0, count($options) - 1));
    }

    /**
     * @param  array<string, mixed>  $rawOption
     */
    private function parseIsCorrect(array $rawOption): bool
    {
        foreach (['is_correct', 'isCorrect', 'correct'] as $field) {
            if (array_key_exists($field, $rawOption)) {
                return $this->parseBoolean($rawOption[$field]);
            }
        }

        return false;
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', '1', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'no', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
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
