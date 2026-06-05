<?php

namespace App\Services;

use App\Imports\ParticipantsImport;
use App\Imports\ParticipantsRowCounter;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ParticipantImportService
{
    public const BACKGROUND_ROW_THRESHOLD = 50;

    /**
     * @return array{queued: bool, message: string, count: int}
     */
    public function import(string $storedPath): array
    {
        $rowCount = $this->countRows($storedPath);

        if ($rowCount > self::BACKGROUND_ROW_THRESHOLD) {
            (new ParticipantsImport($storedPath))
                ->queue($storedPath, 'local');

            return [
                'queued' => true,
                'count' => $rowCount,
                'message' => "Import {$rowCount} peserta sedang diproses di background. Pastikan queue worker berjalan (`php artisan queue:work`). Data akan muncul setelah proses selesai.",
            ];
        }

        Excel::import(new ParticipantsImport($storedPath), Storage::disk('local')->path($storedPath));

        return [
            'queued' => false,
            'count' => $rowCount,
            'message' => $rowCount > 0
                ? "{$rowCount} peserta berhasil diimpor."
                : 'Tidak ada data peserta untuk diimpor.',
        ];
    }

    public function countRows(string $storedPath): int
    {
        $rows = Excel::toCollection(new ParticipantsRowCounter, $storedPath, 'local')->first();

        return $rows?->count() ?? 0;
    }
}
