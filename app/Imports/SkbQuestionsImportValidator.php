<?php

namespace App\Imports;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * Deliberately a single flat class rather than WithMultipleSheets wrapping a
 * named sub-sheet importer (unlike QuestionsImportValidator): Maatwebsite
 * Excel only ever registers WithEvents listeners for the top-level import
 * object passed to Excel::import(), so a nested sheet-scoped class's
 * registerEvents() would never actually run. This also sidesteps the
 * CSV/sheet-name mismatch described on SkbQuestionsImport.
 */
class SkbQuestionsImportValidator implements ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use ValidatesSkbQuestionImportRows;

    private int $rowOffset = 0;

    /** @var array<int, array{row: ?int, column: ?string, value: ?string, message: string}> */
    private array $errors = [];

    public function collection(Collection $rows): void
    {
        $rows = $this->filterSkbQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $this->errors = array_merge(
            $this->errors,
            $this->collectSkbQuestionBusinessRuleErrors($rows, $this->rowOffset),
        );

        $this->rowOffset += $rows->count();
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->rowOffset === 0) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', [[
                        'row' => null,
                        'column' => null,
                        'value' => null,
                        'message' => 'Tidak ada baris data pada sheet Template Soal SKB. Pastikan file berisi header dan minimal 1 baris soal.',
                    ]]));
                }

                if ($this->errors !== []) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', $this->errors));
                }
            },
        ];
    }

    public function rules(): array
    {
        return [
            '*.content' => ['required', 'string'],
        ];
    }
}
