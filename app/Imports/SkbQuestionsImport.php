<?php

namespace App\Imports;

use App\Imports\Concerns\DeletesStoredImportFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Deliberately does NOT implement WithMultipleSheets (see SkbQuestionsSheetImport
 * for why) NOR WithChunkReading: Maatwebsite Excel dispatches each chunk through
 * the app's real queue connection (not run inline), regardless of whether this
 * class implements ShouldQueue. On an environment where QUEUE_CONNECTION isn't
 * "sync" and no worker is running (the normal case outside tests), a chunked
 * "synchronous" import silently does nothing — the chunk jobs just sit in the
 * `jobs` table. This class is only used for the small (<=100 rows) path via
 * Excel::import(), so reading the whole file in one collection() call is fine.
 */
class SkbQuestionsImport implements ToCollection, WithEvents, WithHeadingRow
{
    use DeletesStoredImportFile;

    private SkbQuestionsSheetImport $sheetImport;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
    ) {
        $this->sheetImport = new SkbQuestionsSheetImport($jabatanSkbId, $createdBy);
    }

    public function collection(Collection $rows): void
    {
        $this->sheetImport->collection($rows);
    }
}
