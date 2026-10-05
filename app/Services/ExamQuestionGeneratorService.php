<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExamQuestionGeneratorService
{
    public const TOTAL_QUESTIONS = 110;

    /** @var array<string, int> */
    public const COUNTS_BY_SUBJECT = [
        SubjectCode::Twk->value => 30,
        SubjectCode::Tiu->value => 35,
        SubjectCode::Tkp->value => 45,
    ];

    /** @var list<SubjectCode> */
    public const SUBJECT_ORDER = [
        SubjectCode::Twk,
        SubjectCode::Tiu,
        SubjectCode::Tkp,
    ];

    /**
     * @return array<string, array{required: int, available: int}>
     */
    public function availability(string $difficulty = 'all'): array
    {
        $result = [];

        foreach (self::SUBJECT_ORDER as $code) {
            $required = self::COUNTS_BY_SUBJECT[$code->value];
            $result[$code->value] = [
                'required' => $required,
                'available' => $this->baseQuery($code, $difficulty)->count(),
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, int> question IDs in exam order
     */
    public function generate(string $difficulty = 'all'): Collection
    {
        // Picks only the IDs (not every question's full content) and checks
        // the bank from what it picked: a short pick means too few questions,
        // so no separate COUNT per subject is needed when hundreds of
        // participants start at once.
        $picked = [];

        foreach (self::SUBJECT_ORDER as $code) {
            $required = self::COUNTS_BY_SUBJECT[$code->value];

            $ids = $this->baseQuery($code, $difficulty)
                ->inRandomOrder()
                ->limit($required)
                ->pluck('id');

            if ($ids->count() < $required) {
                throw ValidationException::withMessages([
                    'difficulty' => $this->insufficientMessage($code, $ids->count(), $required),
                ]);
            }

            $picked[] = $ids->shuffle();
        }

        $sortOrder = 1;

        return collect($picked)
            ->flatten()
            ->map(function ($id) use (&$sortOrder) {
                return ['id' => (int) $id, 'sort_order' => $sortOrder++];
            })
            ->values();
    }

    public function assertSufficientQuestions(string $difficulty = 'all'): void
    {
        $errors = [];

        foreach ($this->availability($difficulty) as $code => $stats) {
            if ($stats['available'] < $stats['required']) {
                $errors['difficulty'] = $this->insufficientMessage(SubjectCode::from($code), $stats['available'], $stats['required']);
                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function insufficientMessage(SubjectCode $code, int $available, int $required): string
    {
        return "Bank soal {$code->label()} tidak cukup. Tersedia {$available} soal, dibutuhkan {$required}.";
    }

    private function baseQuery(SubjectCode $code, string $difficulty)
    {
        return Question::query()
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('code', $code))
            ->when($difficulty !== 'all', fn ($query) => $query->where('difficulty', $difficulty));
    }
}
