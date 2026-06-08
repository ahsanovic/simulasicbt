<?php

namespace App\Imports;

use App\Models\QuestionImportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

class QuestionsQueuedImport implements ShouldQueue, WithChunkReading, WithEvents, WithMultipleSheets
{
    use Importable;

    public function __construct(
        private readonly ?int $createdBy = null,
        private readonly ?string $storedPath = null,
        private readonly ?int $importJobId = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function sheets(): array
    {
        return [
            'Template Soal' => new QuestionsSheetImport($this->createdBy, $this->importJobId),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->storedPath) {
                    Storage::disk('local')->delete($this->storedPath);
                }

                if ($this->importJobId) {
                    QuestionImportJob::query()->find($this->importJobId)?->markCompleted();
                }
            },
            ImportFailed::class => function (ImportFailed $event) {
                if ($this->importJobId) {
                    QuestionImportJob::query()
                        ->find($this->importJobId)
                        ?->markFailed($event->getException()->getMessage());
                }
            },
        ];
    }
}
