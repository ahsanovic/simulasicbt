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
        $this->assertSufficientQuestions($difficulty);

        $questionIds = collect();
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
                $questionIds->push([
                    'id' => $question->id,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        return $questionIds;
    }

    public function assertSufficientQuestions(string $difficulty = 'all'): void
    {
        $errors = [];

        foreach ($this->availability($difficulty) as $code => $stats) {
            if ($stats['available'] < $stats['required']) {
                $label = SubjectCode::from($code)->label();
                $errors['difficulty'] = "Bank soal {$label} tidak cukup. Tersedia {$stats['available']} soal, dibutuhkan {$stats['required']}.";
                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function baseQuery(SubjectCode $code, string $difficulty)
    {
        return Question::query()
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('code', $code))
            ->when($difficulty !== 'all', fn ($query) => $query->where('difficulty', $difficulty));
    }
}
