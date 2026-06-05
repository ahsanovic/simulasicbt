<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ParticipantsImportTemplate implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Peserta';
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'username',
            'password',
            'nip',
            'instansi_id',
            'is_pegawai',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Budi Santoso',
                'budi.santoso@example.com',
                'budi.santoso',
                'Password123!',
                '198501012006011001',
                '1',
                '1',
            ],
            [
                'Siti Aminah',
                'siti.umum@example.com',
                'siti.umum',
                'Password123!',
                '',
                '',
                '0',
            ],
        ];
    }
}
