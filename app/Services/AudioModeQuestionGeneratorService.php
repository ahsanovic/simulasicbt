<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AudioModeQuestionGeneratorService
{
    public const DEFAULT_LIMIT = 20;

    /** @var list<string> */
    public const TIU_NUMERIC_GROUP_SLUG = 'kemampuan-numerik';

    /**
     * @return list<int>
     */
    public function generate(SubjectCode $subjectCode, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min(50, $limit));

        $available = $this->baseQuery($subjectCode)->count();

        if ($available < 1) {
            throw ValidationException::withMessages([
                'subject' => "Bank soal {$subjectCode->label()} belum tersedia untuk Audio Mode.",
            ]);
        }

        $actualLimit = min($limit, $available);

        return $this->baseQuery($subjectCode)
            ->inRandomOrder()
            ->limit($actualLimit)
            ->pluck('id')
            ->all();
    }

    /**
     * @return Collection<int, Question>
     */
    public function loadQuestions(array $questionIds): Collection
    {
        if ($questionIds === []) {
            return collect();
        }

        $questions = Question::query()
            ->with(['subject', 'options'])
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');

        return collect($questionIds)
            ->map(fn (int $id) => $questions->get($id))
            ->filter();
    }

    public function availableCount(SubjectCode $subjectCode): int
    {
        return $this->baseQuery($subjectCode)->count();
    }

    private function baseQuery(SubjectCode $subjectCode)
    {
        return Question::query()
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('code', $subjectCode))
            ->when(
                $subjectCode === SubjectCode::Tiu,
                fn ($query) => $query->whereHas(
                    'material',
                    fn ($materialQuery) => $materialQuery->whereHas(
                        'materialGroup',
                        fn ($groupQuery) => $groupQuery->where('slug', '!=', self::TIU_NUMERIC_GROUP_SLUG),
                    )->orWhereNull('material_group_id'),
                ),
            );
    }
}
