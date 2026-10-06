<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Question;
use App\Support\QuestionPool;
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
        $picked = $this->pickFromPool($difficulty) ?? $this->pickFromDatabase($difficulty);
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

    /**
     * Random IDs per subject from the shared cached pools: no ORDER BY RAND()
     * over the bank for every participant who presses "Mulai". The pick is
     * re-checked against the database in one indexed query; null (use the
     * database) when the pool is off, unreachable, too small or stale.
     *
     * @return list<list<int>>|null
     */
    private function pickFromPool(string $difficulty): ?array
    {
        $picked = [];

        foreach (self::SUBJECT_ORDER as $code) {
            $required = self::COUNTS_BY_SUBJECT[$code->value];
            $pool = QuestionPool::ids('skd', "{$code->value}:{$difficulty}", fn () => $this->baseQuery($code, $difficulty)->pluck('id')->all());

            if ($pool === null || count($pool) < $required) {
                return null; // the database path gives the exact "bank too small" answer
            }

            $picked[] = QuestionPool::sample($pool, $required);
        }

        // Every pick must still be active, of the requested difficulty and of
        // the subject block it was drawn for: a bulk update that skipped the
        // model events (e.g. a question moved to another subject) leaves the
        // cached pool stale.
        $subjectOf = Question::query()
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->whereKey(array_merge(...$picked))
            ->where('questions.is_active', true)
            ->when($difficulty !== 'all', fn ($query) => $query->where('questions.difficulty', $difficulty))
            ->pluck('subjects.code', 'questions.id');

        foreach (self::SUBJECT_ORDER as $block => $code) {
            foreach ($picked[$block] as $id) {
                if (($subjectOf[$id] ?? null) !== $code->value) {
                    QuestionPool::bust('skd');

                    return null;
                }
            }
        }

        return $picked;
    }

    /**
     * The original pick: only the IDs (not every question's content), and the
     * bank is checked from what it picked, so no separate COUNT is needed.
     *
     * @return list<list<int>>
     */
    private function pickFromDatabase(string $difficulty): array
    {
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

            $picked[] = $ids->map(fn ($id) => (int) $id)->shuffle()->all();
        }

        return $picked;
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
