<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GhostRivalPulledAhead extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $rivalAlias,
        public readonly int $gapPoints,
        public readonly int $gapIncrease,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'ghost_rival_pulled_ahead',
            'rival_alias' => $this->rivalAlias,
            'gap_points' => $this->gapPoints,
            'gap_increase' => $this->gapIncrease,
            'message' => "{$this->rivalAlias} memperlebar jarak +{$this->gapIncrease} poin — kini unggul {$this->gapPoints} poin.",
        ];
    }
}
