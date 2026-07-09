<?php

namespace App\Enums;

enum HelpItem: string
{
    case SkipTracker = 'skip_tracker';
    case FiftyFifty = 'fifty_fifty';

    public function label(): string
    {
        return match ($this) {
            self::SkipTracker => 'Skip Tracker',
            self::FiftyFifty => '50:50 Eliminator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SkipTracker => 'Notifikasi saat terjebak >60 detik di satu soal, plus aksi tandai & loncat ke soal berikutnya.',
            self::FiftyFifty => 'Sembunyikan 2 pilihan jawaban paling salah pada soal TWK/TIU saat simulasi.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SkipTracker => '⏱️',
            self::FiftyFifty => '🎯',
        };
    }

    public function price(): int
    {
        return match ($this) {
            self::SkipTracker => 200,
            self::FiftyFifty => 500,
        };
    }

    public function usageHint(): string
    {
        return match ($this) {
            self::SkipTracker => 'Aktifkan sekali di awal simulasi. Berlaku sepanjang ujian.',
            self::FiftyFifty => 'Pakai per soal TWK/TIU selama simulasi berlangsung.',
        };
    }

    public function availableInDuel(): bool
    {
        return false;
    }

    public function availableInRemedial(): bool
    {
        return false;
    }
}
