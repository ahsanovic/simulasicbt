<?php

namespace App\Imports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * Background (large-file) path: WithChunkReading is safe here because this
 * class is only ever run via ->queue(), which requires (and the UI tells the
 * admin to run) a real queue worker — unlike the sync path, there's no
 * expectation this runs inline without one.
 */
class EventParticipantsQueuedImport implements ShouldQueue, ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use Importable;

    private EventParticipantsSheetImport $sheetImport;

    public function __construct(
        int $eventId,
        bool $requiresSkbJabatan,
        private readonly ?string $storedPath = null,
    ) {
        $this->sheetImport = new EventParticipantsSheetImport($eventId, $requiresSkbJabatan);
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows): void
    {
        $this->sheetImport->collection($rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->storedPath) {
                    Storage::disk('local')->delete($this->storedPath);
                }
            },
        ];
    }
}
