<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ValidatesSkbQuestionImportRows
{
    protected function filterSkbQuestionRows(Collection $rows): Collection
    {
        return $rows->filter(fn ($row) => $this->skbQuestionRowHasContent($row))->values();
    }

    protected function skbQuestionRowHasContent(mixed $row): bool
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
    protected function collectSkbQuestionBusinessRuleErrors(Collection $rows, int $rowOffset = 0): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $rowOffset + $index + 2;

            foreach (['a', 'b', 'c', 'd', 'e'] as $label) {
                $optionKey = "option_{$label}";

                if ($this->skbImportCellIsBlank($row[$optionKey] ?? null)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'column' => 'Opsi '.strtoupper($label),
                        'value' => $row[$optionKey] ?? null,
                        'message' => 'Pilihan jawaban wajib diisi.',
                    ];
                }
            }

            $correctOptionRaw = $row['correct_option'] ?? null;

            if ($this->skbImportCellIsBlank($correctOptionRaw)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Jawaban Benar',
                    'value' => $correctOptionRaw,
                    'message' => 'Jawaban benar wajib diisi.',
                ];

                continue;
            }

            $correctOption = strtoupper(trim((string) $correctOptionRaw));

            if (! in_array($correctOption, ['A', 'B', 'C', 'D', 'E'], true)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Jawaban Benar',
                    'value' => $correctOptionRaw,
                    'message' => 'Jawaban benar harus A, B, C, D, atau E.',
                ];
            }

            $difficultyRaw = $row['difficulty'] ?? null;

            // Blank difficulty is fine — SkbQuestionsSheetImport defaults it to
            // "medium". Only flag a value that was actually provided but isn't
            // recognized (Excel cells come through as "" rather than null when
            // blank, so this must be a value check, not a Laravel `nullable`
            // rule — `nullable` only exempts true null, not empty string).
            if (! $this->skbImportCellIsBlank($difficultyRaw) && $this->normalizeSkbDifficulty($difficultyRaw) === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Tingkat Kesulitan',
                    'value' => $difficultyRaw,
                    'message' => 'Tingkat kesulitan harus easy/medium/hard (atau mudah/sedang/sulit).',
                ];
            }
        }

        return $errors;
    }

    protected function skbImportCellIsBlank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }

    /**
     * Normalizes a difficulty cell to "easy"/"medium"/"hard", accepting the
     * Indonesian labels shown in the admin UI's dropdown as synonyms. Returns
     * null for blank input or a value that doesn't match either language.
     */
    protected function normalizeSkbDifficulty(mixed $raw): ?string
    {
        $value = Str::lower(trim((string) ($raw ?? '')));

        if ($value === '') {
            return null;
        }

        $map = [
            'easy' => 'easy',
            'mudah' => 'easy',
            'medium' => 'medium',
            'sedang' => 'medium',
            'hard' => 'hard',
            'sulit' => 'hard',
        ];

        return $map[$value] ?? null;
    }
}
