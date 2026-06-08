<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QuestionsRowCounter implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Template Soal' => new QuestionsSheetRowCounter,
        ];
    }
}

class QuestionsSheetRowCounter implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        //
    }
}
