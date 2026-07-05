<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DuelQuestionGeneratorService
{
    public const TOTAL_QUESTIONS = 15;

    /** @var array<string, int> */
    public const COUNTS_BY_SUBJECT = [
        SubjectCode::Twk->value => 5,
        SubjectCode::Tiu->value => 5,
        SubjectCode::Tkp->value => 5,
    ];

    /** @var list<SubjectCode> */
    public const SUBJECT_ORDER = [
        SubjectCode::Twk,
        SubjectCode::Tiu,
        SubjectCode::Tkp,
    ];

    /**
     * @return list<int> question IDs in duel order
     */
    public function generate(string $difficulty = 'all'): array
    {
        $this->assertSufficientQuestions($difficulty);

        $questionIds = [];
        $sortOrder = 1;

        foreach (self::SUBJECT_ORDER as $code) {
            $count = self::COUNTS_BY_SUBJECT[$code->value];

            $questions = $this->baseQuery($code, $difficulty)
                ->inRandomOrder()
                ->limit($count)
                ->get()
                ->shuffle()
                ->values();

            foreach ($questions as $question) {
                $questionIds[] = $question->id;
                $sortOrder++;
            }
        }

        return $questionIds;
    }

    public function assertSufficientQuestions(string $difficulty = 'all'): void
    {
        foreach (self::COUNTS_BY_SUBJECT as $code => $required) {
            $available = $this->baseQuery(SubjectCode::from($code), $difficulty)->count();

            if ($available < $required) {
                $label = SubjectCode::from($code)->label();
                throw ValidationException::withMessages([
                    'duel' => "Bank soal {$label} tidak cukup untuk duel. Tersedia {$available}, dibutuhkan {$required}.",
                ]);
            }
        }
    }

    /**
     * @return Collection<int, array{id: int, sort_order: int}>
     */
    public function toQuestionItems(array $questionIds): Collection
    {
        return collect($questionIds)->values()->map(fn (int $id, int $index) => [
            'id' => $id,
            'sort_order' => $index + 1,
        ]);
    }

    private function baseQuery(SubjectCode $code, string $difficulty)
    {
        return Question::query()
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('code', $code))
            ->when($difficulty !== 'all', fn ($query) => $query->where('difficulty', $difficulty));
    }
}
