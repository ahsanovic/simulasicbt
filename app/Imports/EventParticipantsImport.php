<?php

namespace App\Imports;

use App\Imports\Concerns\DeletesStoredImportFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Sync (small-file) path: no WithChunkReading on purpose — see
 * EventParticipantsQueuedImport for why chunking must stay off the class
 * that runs through Excel::import().
 */
class EventParticipantsImport implements ToCollection, WithEvents, WithHeadingRow
{
    use DeletesStoredImportFile;

    private EventParticipantsSheetImport $sheetImport;

    public function __construct(
        int $eventId,
        bool $requiresSkbJabatan,
        private readonly ?string $storedPath = null,
    ) {
        $this->sheetImport = new EventParticipantsSheetImport($eventId, $requiresSkbJabatan);
    }

    public function collection(Collection $rows): void
    {
        $this->sheetImport->collection($rows);
    }
}
