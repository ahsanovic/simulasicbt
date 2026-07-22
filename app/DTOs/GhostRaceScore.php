<?php

namespace App\DTOs;

readonly class GhostRaceScore
{
    public function __construct(
        public int $total,
        public int $skdComponent,
        public int $activityComponent,
        public int $readinessComponent,
    ) {}

    public static function compute(int $skd, int $activity, int $readiness): self
    {
        $total = (int) round($skd * 0.40 + $activity * 0.35 + $readiness * 0.25);

        return new self($total, $skd, $activity, $readiness);
    }

    /** @return array{total: int, skd_component: int, activity_component: int, readiness_component: int} */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'skd_component' => $this->skdComponent,
            'activity_component' => $this->activityComponent,
            'readiness_component' => $this->readinessComponent,
        ];
    }

    /** @param  array{total: int, skd_component: int, activity_component: int, readiness_component: int}  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            total: $data['total'],
            skdComponent: $data['skd_component'],
            activityComponent: $data['activity_component'],
            readinessComponent: $data['readiness_component'],
        );
    }
}
