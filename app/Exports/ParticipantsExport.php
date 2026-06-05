<?php

namespace App\Exports;

use App\Enums\UserRole;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ParticipantsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly ?int $instansiId = null,
    ) {}

    public function title(): string
    {
        if ($this->instansiId) {
            $nama = Instansi::query()->find($this->instansiId)?->nama ?? 'Instansi';

            return mb_substr($nama, 0, 31);
        }

        return 'Semua Peserta';
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Email',
            'Username',
            'NIP',
            'Instansi',
            'Jenis Peserta',
            'Status',
            'Tanggal Daftar',
        ];
    }

    public function collection(): Collection
    {
        return User::query()
            ->with('instansi')
            ->where('role', UserRole::Peserta)
            ->when($this->instansiId, fn ($q) => $q->where('instansi_id', $this->instansiId))
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn (User $user, int $index) => [
                $index + 1,
                $user->name,
                $user->email,
                $user->username ?? '',
                $user->nip ?? '',
                $user->instansi?->nama ?? '',
                $user->is_pegawai ? 'Pegawai Pemprov' : 'Peserta Umum',
                $user->is_active ? 'Aktif' : 'Nonaktif',
                $user->created_at?->format('d/m/Y H:i') ?? '',
            ]);
    }
}
