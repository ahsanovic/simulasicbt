<?php

namespace App\DTOs;

readonly class GhostRaceWeeklyRecap
{
    public function __construct(
        public int $pointsGained,
        public int $gapClosed,
        public int $currentGap,
        public bool $isLeading,
        public bool $trackedSinceToday,
    ) {}

    /** @return array{points_gained: int, gap_closed: int, current_gap: int, is_leading: bool, tracked_since_today: bool, message: string} */
    public function toArray(): array
    {
        return [
            'points_gained' => $this->pointsGained,
            'gap_closed' => $this->gapClosed,
            'current_gap' => $this->currentGap,
            'is_leading' => $this->isLeading,
            'tracked_since_today' => $this->trackedSinceToday,
            'message' => $this->message(),
        ];
    }

    public function message(): string
    {
        if ($this->isLeading) {
            return $this->pointsGained > 0
                ? "Minggu ini Anda naik {$this->pointsGained} poin dan masih memimpin lintasan."
                : 'Anda masih memimpin lintasan minggu ini. Pertahankan!';
        }

        if ($this->gapClosed > 0) {
            return "Minggu ini Anda mengejar {$this->gapClosed} poin — sisa jarak {$this->currentGap} poin.";
        }

        if ($this->pointsGained > 0) {
            return "Minggu ini Anda naik {$this->pointsGained} poin, rival masih unggul {$this->currentGap} poin.";
        }

        return "Rival masih unggul {$this->currentGap} poin minggu ini. Ayo perkecil jaraknya!";
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            pointsGained: $data['points_gained'],
            gapClosed: $data['gap_closed'],
            currentGap: $data['current_gap'],
            isLeading: $data['is_leading'],
            trackedSinceToday: $data['tracked_since_today'],
        );
    }
}
