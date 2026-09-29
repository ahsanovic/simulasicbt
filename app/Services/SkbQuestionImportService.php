<?php

namespace App\Services;

use App\Enums\QuestionImportStatus;
use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Imports\SkbQuestionsImport;
use App\Imports\SkbQuestionsImportValidator;
use App\Imports\SkbQuestionsQueuedImport;
use App\Imports\SkbQuestionsRowCounter;
use App\Models\SkbQuestionImportJob;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class SkbQuestionImportService
{
    use ValidatesSkbQuestionImportRows;

    public const BACKGROUND_ROW_THRESHOLD = 100;

    /**
     * @return array{queued: bool, message: string, count: int, import_job_id?: int}
     */
    public function import(string $storedPath, int $jabatanSkbId, int $createdBy): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount === 0) {
            throw new ImportFailedException(new ImportErrorReport('Import Soal SKB Gagal', [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'Tidak ada baris data pada sheet Template Soal SKB. Pastikan file berisi header dan minimal 1 baris soal.',
            ]]));
        }

        $this->validateFile($storedPath);

        Excel::clearResolvedInstance();

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            $importJob = SkbQuestionImportJob::query()->create([
                'jabatan_skb_id' => $jabatanSkbId,
                'user_id' => $createdBy,
                'total_rows' => $rowCount,
                'status' => QuestionImportStatus::Pending,
            ]);

            Excel::queueImport(
                new SkbQuestionsQueuedImport($jabatanSkbId, $createdBy, $storedPath, $importJob->id),
                $storedPath,
                'local',
            );

            return [
                'queued' => true,
                'count' => $rowCount,
                'import_job_id' => $importJob->id,
                'message' => "Import {$rowCount} soal sedang diproses di background. Progress dapat dipantau di halaman ini.",
            ];
        }

        Excel::import(
            new SkbQuestionsImport($jabatanSkbId, $createdBy, $storedPath),
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
        return $this->filterSkbQuestionRows($this->readTemplateRows($storedPath))->count();
    }

    private function validateFile(string $storedPath): void
    {
        try {
            Excel::import(
                new SkbQuestionsImportValidator,
                Storage::disk('local')->path($storedPath),
            );
        } catch (ExcelValidationException $exception) {
            throw new ImportFailedException(
                ImportErrorReport::fromExcelValidation($exception, 'Import Soal SKB Gagal'),
            );
        }
    }

    private function readTemplateRows(string $storedPath): Collection
    {
        $sheets = Excel::toCollection(new SkbQuestionsRowCounter, $storedPath, 'local');

        return $sheets->get(0, collect());
    }
}
