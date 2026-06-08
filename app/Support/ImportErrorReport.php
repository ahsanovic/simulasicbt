<?php

namespace App\Support;

use App\Exceptions\ImportFailedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

class ImportErrorReport
{
    /**
     * @param  array<int, array{row: ?int, column: ?string, value: ?string, message: string}>  $errors
     */
    public function __construct(
        public readonly string $title,
        public readonly array $errors,
        public readonly ?string $summary = null,
    ) {}

    public function total(): int
    {
        return count($this->errors);
    }

    public function summary(): string
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        $total = $this->total();

        return $total === 1
            ? 'Ditemukan 1 kesalahan. Perbaiki file Excel lalu impor ulang.'
            : "Ditemukan {$total} kesalahan. Perbaiki file Excel lalu impor ulang.";
    }

    /**
     * @return array{title: string, summary: string, total: int, errors: array<int, array{row: ?int, column: ?string, value: ?string, message: string}>}
     */
    public function toSession(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary(),
            'total' => $this->total(),
            'errors' => $this->errors,
        ];
    }

    public static function fromExcelValidation(ExcelValidationException $exception, string $title): self
    {
        $errors = collect($exception->failures())
            ->flatMap(fn (Failure $failure) => collect($failure->errors())->map(
                fn (string $message) => self::formatExcelFailure($failure, $message),
            ))
            ->values()
            ->all();

        return new self($title, $errors);
    }

    public static function fromValidationException(ValidationException $exception, string $title): self
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'row' => null,
                    'column' => self::humanizeColumn($field),
                    'value' => null,
                    'message' => $message,
                ];
            }
        }

        return new self($title, $errors);
    }

    public static function fromThrowable(Throwable $throwable, string $title): self
    {
        if ($throwable instanceof ImportFailedException) {
            return $throwable->report;
        }

        if ($throwable instanceof ExcelValidationException) {
            return self::fromExcelValidation($throwable, $title);
        }

        if ($throwable instanceof ValidationException) {
            return self::fromValidationException($throwable, $title);
        }

        if ($throwable instanceof ModelNotFoundException) {
            return new self($title, [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => 'Data referensi tidak ditemukan di database. Pastikan subject_code dan material_slug sesuai sheet Referensi Materi.',
            ]]);
        }

        return new self($title, [[
            'row' => null,
            'column' => null,
            'value' => null,
            'message' => $throwable->getMessage() ?: 'Terjadi kesalahan saat memproses file Excel.',
        ]]);
    }

    /**
     * @return array{row: ?int, column: ?string, value: ?string, message: string}
     */
    private static function formatExcelFailure(Failure $failure, string $message): array
    {
        $column = str($failure->attribute())->afterLast('.')->toString();

        return [
            'row' => $failure->row(),
            'column' => self::humanizeColumn($column),
            'value' => self::stringifyValue($failure->values()[$column] ?? null),
            'message' => $message,
        ];
    }

    private static function humanizeColumn(?string $column): ?string
    {
        if ($column === null || $column === '') {
            return null;
        }

        $labels = [
            'file' => 'File',
            'subject_code' => 'Kode Subjek',
            'material_slug' => 'Slug Materi',
            'content' => 'Isi Soal',
            'explanation' => 'Pembahasan',
            'difficulty' => 'Tingkat Kesulitan',
            'correct_option' => 'Jawaban Benar',
            'name' => 'Nama',
            'email' => 'Email',
            'username' => 'Username',
            'password' => 'Password',
            'nip' => 'NIP',
            'instansi_id' => 'ID Instansi',
            'is_pegawai' => 'Pegawai',
        ];

        return $labels[$column] ?? str($column)->replace('_', ' ')->title()->toString();
    }

    private static function stringifyValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $stringValue = (string) $value;

        return mb_strlen($stringValue) > 80
            ? mb_substr($stringValue, 0, 80).'…'
            : $stringValue;
    }
}
