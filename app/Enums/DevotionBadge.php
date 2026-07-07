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
            self::PejuangAkuntabel => 'text-emerald-600 bg-emerald-50',
            self::RekanKompeten, self::AbdiHarmonis, self::PenggerakAdaptif => 'text-indigo-600 bg-indigo-50',
            self::TeladanLoyal => 'text-amber-700 bg-amber-50 border border-amber-200',
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
            self::TeladanLoyal => 'amber',
            default => 'indigo',
        };
    }

    /** @return array{value: string, label: string, description: string, classes: string, min_xp: int, tooltip_theme: string} */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'classes' => $this->badgeClasses(),
            'min_xp' => $this->minXp(),
            'tooltip_theme' => $this->tooltipTheme(),
        ];
    }
}
