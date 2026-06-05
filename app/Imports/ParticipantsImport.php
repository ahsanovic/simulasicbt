<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class ParticipantsImport implements ShouldQueue, ToModel, WithChunkReading, WithEvents, WithHeadingRow, WithValidation
{
    use Importable;

    public function __construct(
        private readonly ?string $storedPath = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function model(array $row): User
    {
        $isPegawai = filter_var($row['is_pegawai'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'username' => $row['username'] ?? null,
            'password' => Hash::make($row['password'] ?? 'password'),
            'nip' => $isPegawai ? ($row['nip'] ?? null) : null,
            'instansi_id' => $isPegawai && ! empty($row['instansi_id']) ? (int) $row['instansi_id'] : null,
            'is_pegawai' => $isPegawai,
            'role' => UserRole::Peserta,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string'],
            '*.email' => ['required', 'email', 'unique:users,email'],
            '*.nip' => ['nullable', 'string', 'unique:users,nip'],
            '*.instansi_id' => ['nullable', 'integer', 'exists:instansis,id'],
            '*.is_pegawai' => ['nullable'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->storedPath) {
                    Storage::disk('local')->delete($this->storedPath);
                }
            },
        ];
    }
}
