<?php

namespace App\Imports;

use App\Imports\Concerns\DeletesStoredImportFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QuestionsQueuedImport implements ShouldQueue, WithChunkReading, WithEvents, WithMultipleSheets
{
    use DeletesStoredImportFile;
    use Importable;

    public function __construct(
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function sheets(): array
    {
        return [
            'Template Soal' => new QuestionsSheetImport($this->createdBy),
        ];
    }
}
