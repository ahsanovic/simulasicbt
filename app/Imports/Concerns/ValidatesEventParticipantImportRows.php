<?php

namespace App\Imports\Concerns;

use App\Models\EventSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ValidatesEventParticipantImportRows
{
    protected function filterEventParticipantRows(Collection $rows): Collection
    {
        return $rows->filter(fn ($row) => $this->eventParticipantRowHasContent($row))->values();
    }

    protected function eventParticipantRowHasContent(mixed $row): bool
    {
        $values = $row instanceof Collection ? $row->toArray() : (array) $row;

        foreach ($values as $value) {
            if (filled($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{row: ?int, column: ?string, value: ?string, message: string}>
     */
    protected function collectEventParticipantBusinessRuleErrors(Collection $rows, bool $requiresSkbJabatan, int $eventId, int $rowOffset = 0): array
    {
        $errors = [];

        $sessionNames = EventSession::query()
            ->where('event_id', $eventId)
            ->pluck('name')
            ->map(fn ($name) => Str::lower($name))
            ->all();

        foreach ($rows as $index => $row) {
            $rowNumber = $rowOffset + $index + 2;

            $sesi = trim((string) ($row['sesi'] ?? ''));

            if ($sesi === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Sesi',
                    'value' => $sesi,
                    'message' => 'Sesi wajib diisi dan harus cocok dengan nama sesi yang sudah dibuat.',
                ];
            } elseif (! in_array(Str::lower($sesi), $sessionNames, true)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Sesi',
                    'value' => $sesi,
                    'message' => 'Nama sesi tidak ditemukan di event ini. Cek halaman "Kelola Sesi".',
                ];
            }

            if ($this->eventParticipantCellIsBlank($row['nama'] ?? null)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Nama',
                    'value' => $row['nama'] ?? null,
                    'message' => 'Nama wajib diisi.',
                ];
            }

            $nik = trim((string) ($row['nik'] ?? ''));

            if ($nik === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'NIK',
                    'value' => $nik,
                    'message' => 'NIK wajib diisi.',
                ];
            } elseif (! ctype_digit($nik) || strlen($nik) !== 16) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'NIK',
                    'value' => $nik,
                    'message' => 'NIK harus berupa 16 digit angka.',
                ];
            }

            if ($this->eventParticipantCellIsBlank($row['jabatan'] ?? null)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => 'Jabatan',
                    'value' => $row['jabatan'] ?? null,
                    'message' => $requiresSkbJabatan
                        ? 'Jabatan wajib diisi dan harus cocok dengan nama Jabatan SKB.'
                        : 'Jabatan wajib diisi.',
                ];
            }
        }

        return $errors;
    }

    protected function eventParticipantCellIsBlank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }
}
