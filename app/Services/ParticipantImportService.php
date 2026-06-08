<?php

namespace App\Services;

use App\Exceptions\ImportFailedException;
use App\Imports\ParticipantsImport;
use App\Imports\ParticipantsRowCounter;
use App\Support\ImportErrorReport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class ParticipantImportService
{
    public const BACKGROUND_ROW_THRESHOLD = 50;

    /**
     * @return array{queued: bool, message: string, count: int}
     */
    public function import(string $storedPath): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount === 0) {
            throw new ImportFailedException(new ImportErrorReport('Import Peserta Gagal', [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'File Excel tidak memiliki baris data. Pastikan sheet berisi header dan minimal 1 baris peserta.',
            ]]));
        }

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            Excel::clearResolvedInstance();

            (new ParticipantsImport($storedPath))
                ->queue($storedPath, 'local');

            return [
                'queued' => true,
                'count' => $rowCount,
                'message' => "Import {$rowCount} peserta sedang diproses di background. Pastikan queue worker berjalan (`php artisan queue:work`). Data akan muncul setelah proses selesai.",
            ];
        }

        try {
            Excel::import(new ParticipantsImport($storedPath), Storage::disk('local')->path($storedPath));
        } catch (ExcelValidationException $exception) {
            throw new ImportFailedException(
                ImportErrorReport::fromExcelValidation($exception, 'Import Peserta Gagal'),
            );
        }

        return [
            'queued' => false,
            'count' => $rowCount,
            'message' => "{$rowCount} peserta berhasil diimpor.",
        ];
    }

    public function countRows(string $storedPath): int
    {
        $rows = Excel::toCollection(new ParticipantsRowCounter, $storedPath, 'local')->first();

        return $rows?->count() ?? 0;
    }
}
