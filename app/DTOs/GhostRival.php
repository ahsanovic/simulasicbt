<?php

namespace App\DTOs;

readonly class GhostRival
{
    public function __construct(
        public string $alias,
        public GhostRaceScore $score,
        public ?string $lastActivity,
        public ?int $bestSkdTotal,
        public bool $isSynthetic,
        public ?int $rivalUserId = null,
        public ?int $rank = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'score' => $this->score->toArray(),
            'last_activity' => $this->lastActivity,
            'best_skd_total' => $this->bestSkdTotal,
            'is_synthetic' => $this->isSynthetic,
            'rival_user_id' => $this->rivalUserId,
            'rank' => $this->rank,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            alias: $data['alias'],
            score: GhostRaceScore::fromArray($data['score']),
            lastActivity: $data['last_activity'],
            bestSkdTotal: $data['best_skd_total'],
            isSynthetic: $data['is_synthetic'],
            rivalUserId: $data['rival_user_id'] ?? null,
            rank: $data['rank'] ?? null,
        );
    }
}
