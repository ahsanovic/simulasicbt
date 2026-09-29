<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SkbQuestionsImportTemplate implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Soal SKB';
    }

    public function headings(): array
    {
        return [
            'content',
            'explanation',
            'difficulty',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'option_e',
            'correct_option',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Contoh soal SKB: Apa tugas utama jabatan ini dalam pelayanan publik?',
                'Pembahasan opsional',
                'medium',
                'Pilihan A',
                'Pilihan B',
                'Pilihan C',
                'Pilihan D',
                'Pilihan E',
                'a',
            ],
        ];
    }
}
