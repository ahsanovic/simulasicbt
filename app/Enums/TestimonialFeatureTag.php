<?php

namespace App\Enums;

enum TestimonialFeatureTag: string
{
    case SimulasiCBT = 'simulasi_cbt';
    case AudioMode = 'audio_mode';
    case Duel1v1 = 'duel_1v1';
    case AnalisisPolaPanik = 'analisis_pola_panik';
    case EvaluasiRapor = 'evaluasi_rapor';
    case Pembahasan = 'pembahasan';
    case ManajemenWaktu = 'manajemen_waktu';
    case Leaderboard = 'leaderboard';

    public function label(): string
    {
        return match ($this) {
            self::SimulasiCBT => 'Simulasi CBT',
            self::AudioMode => 'Audio Mode',
            self::Duel1v1 => 'Duel 1v1',
            self::AnalisisPolaPanik => 'Analisis Pola Panik',
            self::EvaluasiRapor => 'Evaluasi & Rapor AI',
            self::Pembahasan => 'Pembahasan Soal',
            self::ManajemenWaktu => 'Manajemen Waktu',
            self::Leaderboard => 'Leaderboard',
        };
    }

    public function hashtag(): string
    {
        return '#'.str_replace(' ', '', $this->label());
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tag) => [$tag->value => $tag->label()])
            ->all();
    }
}
