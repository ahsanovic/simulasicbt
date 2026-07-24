<?php

namespace App\Enums;

enum LearningPlanTaskCategory: string
{
    case TryOut = 'try_out';
    case Drill = 'drill';
    case Materi = 'materi';
    case Audio = 'audio';
    case KartuSakti = 'kartu_sakti';
    case Review = 'review';
    case Duel = 'duel';
    case Evaluasi = 'evaluasi';
    case Catatan = 'catatan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::TryOut => 'Try Out',
            self::Drill => 'Drill',
            self::Materi => 'Materi',
            self::Audio => 'Audio',
            self::KartuSakti => 'Kartu Sakti',
            self::Review => 'Review',
            self::Duel => 'Duel',
            self::Evaluasi => 'Evaluasi',
            self::Catatan => 'Catatan',
            self::Lainnya => 'Lainnya',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::TryOut => '📝',
            self::Drill => '🎯',
            self::Materi => '📚',
            self::Audio => '🎧',
            self::KartuSakti => '✨',
            self::Review => '🔍',
            self::Duel => '⚡',
            self::Evaluasi => '📊',
            self::Catatan => '📌',
            self::Lainnya => '🗂️',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::TryOut => 'bg-indigo-100 text-indigo-700',
            self::Drill => 'bg-orange-100 text-orange-700',
            self::Materi => 'bg-sky-100 text-sky-700',
            self::Audio => 'bg-violet-100 text-violet-700',
            self::KartuSakti => 'bg-amber-100 text-amber-800',
            self::Review => 'bg-teal-100 text-teal-700',
            self::Duel => 'bg-rose-100 text-rose-700',
            self::Evaluasi => 'bg-emerald-100 text-emerald-700',
            self::Catatan => 'bg-slate-100 text-slate-700',
            self::Lainnya => 'bg-fuchsia-100 text-fuchsia-700',
        };
    }

    public function routeName(): ?string
    {
        return match ($this) {
            self::TryOut => 'peserta.simulasi.index',
            self::Drill => 'peserta.drill.index',
            self::Materi => 'peserta.materi.index',
            self::Audio => 'peserta.audio.index',
            self::KartuSakti => 'peserta.kartu-sakti.index',
            self::Review => 'peserta.history',
            self::Duel => 'peserta.duel.index',
            self::Evaluasi => 'peserta.evaluasi',
            self::Catatan, self::Lainnya => null,
        };
    }
}
