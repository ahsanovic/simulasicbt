<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QuestionsImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new QuestionsImportTemplate,
            new MaterialsReferenceExport,
        ];
    }
}
