<?php

namespace App\Imports;

use App\Exceptions\ImportFailedException;
use App\Imports\Concerns\ValidatesEventParticipantImportRows;
use App\Support\ImportErrorReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;

class EventParticipantsImportValidator implements ToCollection, WithEvents, WithHeadingRow
{
    use ValidatesEventParticipantImportRows;

    /** @var array<int, array{row: ?int, column: ?string, value: ?string, message: string}> */
    private array $errors = [];

    private int $rowCount = 0;

    public function __construct(
        private readonly bool $requiresSkbJabatan,
        private readonly int $eventId,
    ) {}

    public function collection(Collection $rows): void
    {
        $rows = $this->filterEventParticipantRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $this->rowCount += $rows->count();
        $this->errors = array_merge(
            $this->errors,
            $this->collectEventParticipantBusinessRuleErrors($rows, $this->requiresSkbJabatan, $this->eventId),
        );
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->rowCount === 0) {
                    throw new ImportFailedException(new ImportErrorReport('Import Peserta Gagal', [[
                        'row' => null,
                        'column' => null,
                        'value' => null,
                        'message' => 'Tidak ada baris data. Pastikan file berisi header dan minimal 1 baris peserta.',
                    ]]));
                }

                if ($this->errors !== []) {
                    throw new ImportFailedException(new ImportErrorReport('Import Peserta Gagal', $this->errors));
                }
            },
        ];
    }
}
