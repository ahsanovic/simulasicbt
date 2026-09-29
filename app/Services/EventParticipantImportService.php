<?php

namespace App\Services;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesEventParticipantImportRows;
use App\Imports\EventParticipantsImport;
use App\Imports\EventParticipantsImportValidator;
use App\Imports\EventParticipantsQueuedImport;
use App\Imports\EventParticipantsRowCounter;
use App\Support\ImportErrorReport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class EventParticipantImportService
{
    use ValidatesEventParticipantImportRows;

    public const BACKGROUND_ROW_THRESHOLD = 50;

    /**
     * @return array{queued: bool, message: string, count: int}
     */
    public function import(string $storedPath, int $eventId, bool $requiresSkbJabatan): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount === 0) {
            throw new ImportFailedException(new ImportErrorReport('Import Peserta Gagal', [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'Tidak ada baris data. Pastikan file berisi header dan minimal 1 baris peserta.',
            ]]));
        }

        $this->validateFile($storedPath, $requiresSkbJabatan, $eventId);

        Excel::clearResolvedInstance();

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            (new EventParticipantsQueuedImport($eventId, $requiresSkbJabatan, $storedPath))
                ->queue($storedPath, 'local');

            return [
                'queued' => true,
                'count' => $rowCount,
                'message' => "Import {$rowCount} peserta sedang diproses di background. Pastikan queue worker berjalan (`php artisan queue:work`).",
            ];
        }

        Excel::import(
            new EventParticipantsImport($eventId, $requiresSkbJabatan, $storedPath),
            Storage::disk('local')->path($storedPath),
        );

        return [
            'queued' => false,
            'count' => $rowCount,
            'message' => "{$rowCount} peserta berhasil diimpor.",
        ];
    }

    public function countRows(string $storedPath): int
    {
        return $this->filterEventParticipantRows($this->readRows($storedPath))->count();
    }

    private function validateFile(string $storedPath, bool $requiresSkbJabatan, int $eventId): void
    {
        try {
            Excel::import(
                new EventParticipantsImportValidator($requiresSkbJabatan, $eventId),
                Storage::disk('local')->path($storedPath),
            );
        } catch (ExcelValidationException $exception) {
            throw new ImportFailedException(
                ImportErrorReport::fromExcelValidation($exception, 'Import Peserta Gagal'),
            );
        }
    }

    private function readRows(string $storedPath)
    {
        return Excel::toCollection(new EventParticipantsRowCounter, $storedPath, 'local')->first() ?? collect();
    }
}
