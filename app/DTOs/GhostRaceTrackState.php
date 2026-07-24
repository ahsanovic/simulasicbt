<?php

namespace App\DTOs;

use App\Enums\GhostRaceTier;

readonly class GhostRaceTrackState
{
    /**
     * @param  list<array{user_id: int, alias: string, rank: int, race_score: int, is_selected: bool}>  $availableRivals
     * @param  ?array{label: string, position: int}  $checkpoint
     * @param  ?array{label: string, url: string, reason: string}  $cta
     */
    public function __construct(
        public bool $visible,
        public GhostRaceTier $tier,
        public int $userPosition,
        public int $ghostPosition,
        public int $targetPosition,
        public int $gapPoints,
        public GhostRival $ghost,
        public GhostRaceScore $userScore,
        public ?string $formationName,
        public ?array $checkpoint,
        public ?array $cta,
        public ?string $message,
        public bool $notificationsMuted = false,
        public array $availableRivals = [],
        public ?int $selectedRivalUserId = null,
        public ?GhostRaceWeeklyRecap $weeklyRecap = null,
    ) {}

    public static function hidden(): self
    {
        $emptyScore = GhostRaceScore::compute(0, 0, 0);

        return new self(
            visible: false,
            tier: GhostRaceTier::NoFormation,
            userPosition: 0,
            ghostPosition: 0,
            targetPosition: 100,
            gapPoints: 0,
            ghost: new GhostRival('', $emptyScore, null, null, true),
            userScore: $emptyScore,
            formationName: null,
            checkpoint: null,
            cta: null,
            message: null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'visible' => $this->visible,
            'tier' => $this->tier->value,
            'user_position' => $this->userPosition,
            'ghost_position' => $this->ghostPosition,
            'target_position' => $this->targetPosition,
            'gap_points' => $this->gapPoints,
            'ghost' => $this->ghost->toArray(),
            'user_score' => $this->userScore->toArray(),
            'formation_name' => $this->formationName,
            'checkpoint' => $this->checkpoint,
            'cta' => $this->cta,
            'message' => $this->message,
            'notifications_muted' => $this->notificationsMuted,
            'available_rivals' => $this->availableRivals,
            'selected_rival_user_id' => $this->selectedRivalUserId,
            'weekly_recap' => $this->weeklyRecap?->toArray(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            visible: $data['visible'],
            tier: GhostRaceTier::from($data['tier']),
            userPosition: $data['user_position'],
            ghostPosition: $data['ghost_position'],
            targetPosition: $data['target_position'],
            gapPoints: $data['gap_points'],
            ghost: GhostRival::fromArray($data['ghost']),
            userScore: GhostRaceScore::fromArray($data['user_score']),
            formationName: $data['formation_name'],
            checkpoint: $data['checkpoint'],
            cta: $data['cta'],
            message: $data['message'],
            notificationsMuted: $data['notifications_muted'] ?? false,
            availableRivals: $data['available_rivals'] ?? [],
            selectedRivalUserId: $data['selected_rival_user_id'] ?? null,
            weeklyRecap: isset($data['weekly_recap']) ? GhostRaceWeeklyRecap::fromArray($data['weekly_recap']) : null,
        );
    }
}
