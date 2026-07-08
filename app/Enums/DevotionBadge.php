<?php

namespace App\Enums;

enum DevotionBadge: string
{
    case PejuangAkuntabel = 'pejuang_akuntabel';
    case RekanKompeten = 'rekan_kompeten';
    case AbdiHarmonis = 'abdi_harmonis';
    case PenggerakAdaptif = 'penggerak_adaptif';
    case TeladanLoyal = 'teladan_loyal';

    public static function fromXp(int $xp): self
    {
        return match (true) {
            $xp >= 8000 => self::TeladanLoyal,
            $xp >= 5001 => self::PenggerakAdaptif,
            $xp >= 3001 => self::AbdiHarmonis,
            $xp >= 1001 => self::RekanKompeten,
            default => self::PejuangAkuntabel,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PejuangAkuntabel => 'Pejuang Akuntabel',
            self::RekanKompeten => 'Rekan Kompeten',
            self::AbdiHarmonis => 'Abdi Harmonis',
            self::PenggerakAdaptif => 'Penggerak Adaptif',
            self::TeladanLoyal => 'Teladan Loyal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PejuangAkuntabel => 'Belajar dengan jujur dan bertanggung jawab atas waktu sendiri.',
            self::RekanKompeten => 'Sudah mulai menguasai banyak materi dan rajin latihan.',
            self::AbdiHarmonis => 'Sering membantu di kolom diskusi atau aktif berbagi energi positif.',
            self::PenggerakAdaptif => 'Mampu belajar fleksibel, lincah mengejar ketertinggalan materi.',
            self::TeladanLoyal => 'Pengguna paling setia dan menjadi inspirasi di papan peringkat.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PejuangAkuntabel => 'text-emerald-700 bg-emerald-50 ring-emerald-200/70',
            self::RekanKompeten => 'text-sky-700 bg-sky-50 ring-sky-200/70',
            self::AbdiHarmonis => 'text-violet-700 bg-violet-50 ring-violet-200/70',
            self::PenggerakAdaptif => 'text-teal-700 bg-teal-50 ring-teal-200/70',
            self::TeladanLoyal => 'text-amber-800 bg-gradient-to-r from-amber-50 to-yellow-50 ring-amber-300/80 shadow-sm shadow-amber-100/80',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PejuangAkuntabel => 'shield',
            self::RekanKompeten => 'star',
            self::AbdiHarmonis => 'heart',
            self::PenggerakAdaptif => 'bolt',
            self::TeladanLoyal => 'sparkles',
        };
    }

    public function tier(): int
    {
        return match ($this) {
            self::PejuangAkuntabel => 1,
            self::RekanKompeten => 2,
            self::AbdiHarmonis => 3,
            self::PenggerakAdaptif => 4,
            self::TeladanLoyal => 5,
        };
    }

    public function minXp(): int
    {
        return match ($this) {
            self::PejuangAkuntabel => 0,
            self::RekanKompeten => 1001,
            self::AbdiHarmonis => 3001,
            self::PenggerakAdaptif => 5001,
            self::TeladanLoyal => 8000,
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::PejuangAkuntabel => self::RekanKompeten,
            self::RekanKompeten => self::AbdiHarmonis,
            self::AbdiHarmonis => self::PenggerakAdaptif,
            self::PenggerakAdaptif => self::TeladanLoyal,
            self::TeladanLoyal => null,
        };
    }

    /** @return list<self> */
    public static function ladder(): array
    {
        return self::cases();
    }

    public function tooltipTheme(): string
    {
        return match ($this) {
            self::PejuangAkuntabel => 'emerald',
            self::RekanKompeten => 'sky',
            self::AbdiHarmonis => 'violet',
            self::PenggerakAdaptif => 'teal',
            self::TeladanLoyal => 'amber',
        };
    }

    /** @return array{value: string, label: string, description: string, classes: string, min_xp: int, tooltip_theme: string, icon: string, tier: int} */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'classes' => $this->badgeClasses(),
            'min_xp' => $this->minXp(),
            'tooltip_theme' => $this->tooltipTheme(),
            'icon' => $this->icon(),
            'tier' => $this->tier(),
        ];
    }
}
