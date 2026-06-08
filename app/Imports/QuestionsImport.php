<?php

namespace App\Imports;

use App\Enums\SubjectCode;
use App\Exceptions\ImportFailedException;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Services\HtmlSanitizer;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class QuestionsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function __construct(private readonly ?int $createdBy = null) {}

    public function collection(Collection $rows): void
    {
        $businessErrors = $this->collectBusinessRuleErrors($rows);

        if ($businessErrors !== []) {
            throw new ImportFailedException(new ImportErrorReport('Import Soal Gagal', $businessErrors));
        }

        $sanitizer = app(HtmlSanitizer::class);

        DB::transaction(function () use ($rows, $sanitizer) {
            foreach ($rows as $row) {
                $subject = Subject::query()->where('code', $row['subject_code'])->firstOrFail();
                $material = Material::query()
                    ->where('subject_id', $subject->id)
                    ->where('slug', $row['material_slug'])
                    ->firstOrFail();

                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => $sanitizer->sanitize($row['content']),
                    'explanation' => $sanitizer->sanitize($row['explanation'] ?? null),
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                ]);

                $labels = ['a', 'b', 'c', 'd', 'e'];
                $isTkp = $subject->code === SubjectCode::Tkp;

                foreach ($labels as $index => $label) {
                    $contentKey = "option_{$label}";
                    if (empty($row[$contentKey])) {
                        continue;
                    }

                    QuestionOption::query()->create([
                        'question_id' => $question->id,
                        'label' => strtoupper($label),
                        'content_type' => 'text',
                        'content' => $sanitizer->sanitize($row[$contentKey]),
                        'is_correct' => ! $isTkp && strtoupper($row['correct_option'] ?? '') === strtoupper($label),
                        'score_weight' => $isTkp ? (int) ($row["weight_{$label}"] ?? 1) : null,
                        'sort_order' => $index + 1,
                    ]);
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            '*.subject_code' => ['required', 'in:twk,tiu,tkp'],
            '*.material_slug' => ['required', 'string'],
            '*.content' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    private function collectBusinessRuleErrors(Collection $rows): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $subjectCode = strtolower(trim((string) ($row['subject_code'] ?? '')));
            $materialSlug = trim((string) ($row['material_slug'] ?? ''));

            $subject = $subjectCode !== ''
                ? Subject::query()->where('code', $subjectCode)->first()
                : null;

            if ($subjectCode !== '' && $subject === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Kode Subjek',
                    'value' => $row['subject_code'] ?? null,
                    'message' => 'Kode subjek tidak ditemukan. Gunakan twk, tiu, atau tkp.',
                ];

                continue;
            }

            if ($subject !== null && $materialSlug !== '') {
                $materialExists = Material::query()
                    ->where('subject_id', $subject->id)
                    ->where('slug', $materialSlug)
                    ->exists();

                if (! $materialExists) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'column' => 'Slug Materi',
                        'value' => $materialSlug,
                        'message' => 'Materi tidak ditemukan untuk subjek ini. Cek sheet Referensi Materi.',
                    ];
                }
            }

            if ($subject?->code === SubjectCode::Tkp) {
                $errors = array_merge($errors, $this->collectTkpErrors($row, $rowNumber));
            } elseif ($subject !== null) {
                $errors = array_merge($errors, $this->collectNonTkpErrors($row, $rowNumber));
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    private function collectTkpErrors(Collection|array $row, int $rowNumber): array
    {
        $errors = [];
        $weights = [];

        foreach (['a', 'b', 'c', 'd', 'e'] as $label) {
            $weight = (int) ($row["weight_{$label}"] ?? 0);

            if ($weight < 1 || $weight > 5) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Bobot '.strtoupper($label),
                    'value' => $row["weight_{$label}"] ?? null,
                    'message' => 'Bobot TKP harus bernilai 1 sampai 5.',
                ];
            } else {
                $weights[] = $weight;
            }
        }

        if (count($weights) === 5 && count($weights) !== count(array_unique($weights))) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => 'Bobot Opsi',
                'value' => implode(', ', $weights),
                'message' => 'Pada soal TKP, bobot setiap opsi tidak boleh duplikat.',
            ];
        }

        if (count($weights) === 5 && array_values(array_unique($weights)) !== [1, 2, 3, 4, 5]) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => 'Bobot Opsi',
                'value' => implode(', ', $weights),
                'message' => 'Pada soal TKP, bobot harus unik dan berisi angka 1, 2, 3, 4, dan 5.',
            ];
        }

        return $errors;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    private function collectNonTkpErrors(Collection|array $row, int $rowNumber): array
    {
        $correctOption = strtoupper(trim((string) ($row['correct_option'] ?? '')));

        if ($correctOption === '' || ! in_array($correctOption, ['A', 'B', 'C', 'D', 'E'], true)) {
            return [[
                'row' => $rowNumber,
                'column' => 'Jawaban Benar',
                'value' => $row['correct_option'] ?? null,
                'message' => 'Jawaban benar wajib diisi dengan A, B, C, D, atau E.',
            ]];
        }

        return [];
    }
}
