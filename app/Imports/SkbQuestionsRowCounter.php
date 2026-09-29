<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SkbQuestionsRowCounter implements WithMultipleSheets
{
    public function sheets(): array
    {
        // Keyed by sheet index (not name): CSV uploads always load as a single
        // implicit "Worksheet" sheet, so a name-based lookup like the SKD
        // importer uses would throw SheetNotFoundException for every CSV file.
        // Index 0 works for both the named .xlsx template and plain CSV.
        return [
            0 => new SkbQuestionsSheetRowCounter,
        ];
    }
}

class SkbQuestionsSheetRowCounter implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        //
    }
}
