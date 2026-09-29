<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EventParticipantsImportTemplate implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, string>  $sessionNames  real session names for this
     *                                            event, shown as example
     *                                            values so the admin copies
     *                                            a name that actually exists
     *                                            instead of guessing one.
     */
    public function __construct(private readonly array $sessionNames = []) {}

    public function title(): string
    {
        return 'Template Peserta';
    }

    public function headings(): array
    {
        return ['nama', 'nik', 'jabatan', 'sesi'];
    }

    public function array(): array
    {
        $exampleSession = $this->sessionNames[0] ?? 'Sesi 1';

        return [
            ['Contoh Nama Peserta', '3201234567890001', 'Analis Kebijakan', $exampleSession],
        ];
    }
}
