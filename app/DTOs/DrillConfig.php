<?php

namespace App\DTOs;

use App\Enums\DrillFocusMode;
use App\Enums\SubjectCode;

readonly class DrillConfig
{
    /**
     * @param  list<int>  $materialIds
     */
    public function __construct(
        public SubjectCode $subjectCode,
        public array $materialIds,
        public DrillFocusMode $focusMode,
        public int $questionCount,
        public int $durationMinutes,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            subjectCode: SubjectCode::from($data['subject_code']),
            materialIds: array_values(array_map('intval', $data['material_ids'] ?? [])),
            focusMode: DrillFocusMode::from($data['focus_mode'] ?? DrillFocusMode::Mixed->value),
            questionCount: (int) ($data['question_count'] ?? 20),
            durationMinutes: (int) ($data['duration_minutes'] ?? 30),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subject_code' => $this->subjectCode->value,
            'material_ids' => $this->materialIds,
            'focus_mode' => $this->focusMode->value,
            'question_count' => $this->questionCount,
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
