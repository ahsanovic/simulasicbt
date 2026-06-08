<?php

namespace App\Imports;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesQuestionImportRows;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class QuestionsImportValidator implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Template Soal' => new QuestionsSheetImportValidator,
        ];
    }
}

class QuestionsSheetImportValidator implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow, WithValidation
{
    use ValidatesQuestionImportRows;

    private int $rowOffset = 0;

    /** @var array<int, array{row: ?int, column: ?string, value: ?string, message: string}> */
    private array $errors = [];

    public function chunkSize(): int
    {
        return 200;
    }

    public function collection(Collection $rows): void
    {
        $rows = $this->filterQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $this->errors = array_merge(
            $this->errors,
            $this->collectQuestionBusinessRuleErrors($rows, $this->rowOffset),
        );

        $this->rowOffset += $rows->count();
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->rowOffset === 0) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal Gagal', [[
                        'row' => null,
                        'column' => null,
                        'value' => null,
                        'message' => 'Tidak ada baris data pada sheet Template Soal. Pastikan file berisi header dan minimal 1 baris soal.',
                    ]]));
                }

                if ($this->errors !== []) {
                    throw new ImportFailedException(new ImportErrorReport('Import Soal Gagal', $this->errors));
                }
            },
        ];
    }

    public function rules(): array
    {
        return [
            '*.subject_code' => ['required', 'in:twk,tiu,tkp'],
            '*.material_slug' => ['required', 'string'],
            '*.content' => ['required', 'string'],
        ];
    }
}
