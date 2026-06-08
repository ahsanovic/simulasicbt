<?php

namespace App\Enums;

enum QuestionImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu antrian',
            self::Processing => 'Sedang diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
