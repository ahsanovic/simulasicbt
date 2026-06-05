<?php

namespace App\Exports;

use App\Models\Material;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MaterialsReferenceExport implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Referensi Materi';
    }

    public function headings(): array
    {
        return [
            'subject_code',
            'material_slug',
            'material_name',
        ];
    }

    public function collection()
    {
        return Material::query()
            ->with('subject')
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Material $material) => [
                'subject_code' => $material->subject->code->value,
                'material_slug' => $material->slug,
                'material_name' => $material->name,
            ]);
    }
}
