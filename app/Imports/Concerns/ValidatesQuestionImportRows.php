<?php

namespace App\Imports\Concerns;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Support\Collection;

trait ValidatesQuestionImportRows
{
    protected function filterQuestionRows(Collection $rows): Collection
    {
        return $rows->filter(fn ($row) => $this->questionRowHasContent($row))->values();
    }

    protected function questionRowHasContent(mixed $row): bool
    {
        $values = $row instanceof Collection ? $row->toArray() : (array) $row;

        foreach ($values as $value) {
            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    protected function collectQuestionBusinessRuleErrors(Collection $rows, int $rowOffset = 0): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $rowOffset + $index + 2;
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
                $errors = array_merge($errors, $this->collectTkpQuestionErrors($row, $rowNumber));
            } elseif ($subject !== null) {
                $errors = array_merge($errors, $this->collectNonTkpQuestionErrors($row, $rowNumber));
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    protected function collectTkpQuestionErrors(Collection|array $row, int $rowNumber): array
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

        if (count($weights) === 5 && ! $this->tkpQuestionWeightsAreValid($weights)) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => 'Bobot Opsi',
                'value' => implode(', ', $weights),
                'message' => 'Pada soal TKP, bobot harus unik dan berisi angka 1, 2, 3, 4, dan 5.',
            ];
        }

        return $errors;
    }

    protected function tkpQuestionWeightsAreValid(array $weights): bool
    {
        if (count($weights) !== 5) {
            return false;
        }

        $sorted = array_values($weights);
        sort($sorted);

        return $sorted === [1, 2, 3, 4, 5];
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    protected function collectNonTkpQuestionErrors(Collection|array $row, int $rowNumber): array
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
