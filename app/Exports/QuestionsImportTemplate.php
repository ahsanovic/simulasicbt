<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class QuestionsImportTemplate implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Soal';
    }

    public function headings(): array
    {
        return [
            'subject_code',
            'material_slug',
            'content',
            'explanation',
            'difficulty',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'option_e',
            'correct_option',
            'weight_a',
            'weight_b',
            'weight_c',
            'weight_d',
            'weight_e',
        ];
    }

    public function array(): array
    {
        return [
            [
                'twk',
                'nasionalisme',
                'Contoh soal TWK: Apa makna Bhinneka Tunggal Ika?',
                'Pembahasan opsional',
                'medium',
                'Pilihan A',
                'Pilihan B',
                'Pilihan C',
                'Pilihan D',
                'Pilihan E',
                'a',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                'tkp',
                'pelayanan-publik',
                'Contoh soal TKP: Saya selalu memberikan pelayanan terbaik kepada masyarakat.',
                '',
                'easy',
                'Sangat Setuju',
                'Setuju',
                'Netral',
                'Tidak Setuju',
                'Sangat Tidak Setuju',
                '',
                '5',
                '4',
                '3',
                '2',
                '1',
            ],
        ];
    }
}
