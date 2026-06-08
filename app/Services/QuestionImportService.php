<?php

namespace App\Services;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesQuestionImportRows;
use App\Imports\QuestionsImport;
use App\Imports\QuestionsImportValidator;
use App\Imports\QuestionsQueuedImport;
use App\Imports\QuestionsRowCounter;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class QuestionImportService
{
    use ValidatesQuestionImportRows;

    public const BACKGROUND_ROW_THRESHOLD = 100;

    /**
     * @return array{queued: bool, message: string, count: int}
     */
    public function import(string $storedPath, int $createdBy): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount === 0) {
            throw new ImportFailedException(new ImportErrorReport('Import Soal Gagal', [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'Tidak ada baris data pada sheet Template Soal. Pastikan file berisi header dan minimal 1 baris soal.',
            ]]));
        }

        $this->validateFile($storedPath);

        // Validasi chunk (sync) melakukan garbage collect pada Reader internal.
        // Tanpa reset, queue import gagal serialize: "spreadsheet" does not exist.
        Excel::clearResolvedInstance();

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            Excel::queueImport(
                new QuestionsQueuedImport($createdBy, $storedPath),
                $storedPath,
                'local',
            );

            return [
                'queued' => true,
                'count' => $rowCount,
                'message' => "Import {$rowCount} soal sedang diproses di background. Pastikan queue worker berjalan (`php artisan queue:work`). Soal akan muncul setelah proses selesai.",
            ];
        }

        Excel::import(
            new QuestionsImport($createdBy, $storedPath),
            Storage::disk('local')->path($storedPath),
        );

        return [
            'queued' => false,
            'count' => $rowCount,
            'message' => "{$rowCount} soal berhasil diimpor.",
        ];
    }

    public function countRows(string $storedPath): int
    {
        return $this->filterQuestionRows($this->readTemplateRows($storedPath))->count();
    }

    private function validateFile(string $storedPath): void
    {
        try {
            Excel::import(
                new QuestionsImportValidator,
                Storage::disk('local')->path($storedPath),
            );
        } catch (ExcelValidationException $exception) {
            throw new ImportFailedException(
                ImportErrorReport::fromExcelValidation($exception, 'Import Soal Gagal'),
            );
        }
    }

    private function readTemplateRows(string $storedPath): Collection
    {
        $sheets = Excel::toCollection(new QuestionsRowCounter, $storedPath, 'local');

        return $sheets->get('Template Soal', collect());
    }
}
