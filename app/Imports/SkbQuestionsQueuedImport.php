<?php

namespace App\Imports;

use App\Models\SkbQuestionImportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

/**
 * See SkbQuestionsImport for why this does not implement WithMultipleSheets.
 */
class SkbQuestionsQueuedImport implements ShouldQueue, ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use Importable;

    private SkbQuestionsSheetImport $sheetImport;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
        private readonly ?int $importJobId = null,
    ) {
        $this->sheetImport = new SkbQuestionsSheetImport($jabatanSkbId, $createdBy, $importJobId);
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

                if ($this->importJobId) {
                    SkbQuestionImportJob::query()->find($this->importJobId)?->markCompleted();
                }
            },
            ImportFailed::class => function (ImportFailed $event) {
                if ($this->importJobId) {
                    SkbQuestionImportJob::query()
                        ->find($this->importJobId)
                        ?->markFailed($event->getException()->getMessage());
                }
            },
        ];
    }
}
