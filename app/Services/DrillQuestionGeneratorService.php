<?php

namespace App\Services;

use App\DTOs\DrillConfig;
use App\Enums\DrillFocusMode;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\SubjectCode;
use App\Models\ExamAnswer;
use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DrillQuestionGeneratorService
{
    public const MIN_QUESTIONS = 5;

    public const MAX_QUESTIONS = 50;

    /** @var list<int> */
    public const QUESTION_PRESETS = [10, 20, 30, 50];

    public const SECONDS_PER_QUESTION = 90;

    public const MIN_DURATION_MINUTES = 5;

    public const MAX_DURATION_MINUTES = 120;

    private const MIXED_WEAK_RATIO = 0.7;

    /**
     * @return list<int>
     */
    public function generate(DrillConfig $config, User $user): array
    {
        $limit = max(self::MIN_QUESTIONS, min(self::MAX_QUESTIONS, $config->questionCount));
        $materialIds = $this->resolveMaterialIds($config);

        if ($materialIds === []) {
            throw ValidationException::withMessages([
                'materials' => 'Pilih minimal satu sub-materi yang tersedia.',
            ]);
        }

        $pool = $this->baseQuery($config->subjectCode, $materialIds);
        $available = $pool->count();

        if ($available < 1) {
            throw ValidationException::withMessages([
                'materials' => "Bank soal {$config->subjectCode->label()} untuk sub-materi terpilih belum tersedia.",
            ]);
        }

        $actualLimit = min($limit, $available);

        $questionIds = match ($config->focusMode) {
            DrillFocusMode::Weak => $this->selectWeakQuestions($user, $config->subjectCode, $materialIds, $actualLimit),
            DrillFocusMode::Random => $this->selectRandomQuestions($pool, $actualLimit),
            DrillFocusMode::Mixed => $this->selectMixedQuestions($user, $config->subjectCode, $materialIds, $pool, $actualLimit),
        };

        if ($questionIds === []) {
            throw ValidationException::withMessages([
                'focus_mode' => 'Tidak ada soal yang cocok untuk mode fokus ini. Coba mode acak atau pilih sub-materi lain.',
            ]);
        }

        return $questionIds;
    }

    public function suggestedDurationMinutes(int $questionCount): int
    {
        $minutes = (int) ceil(($questionCount * self::SECONDS_PER_QUESTION) / 60);

        return max(self::MIN_DURATION_MINUTES, min(self::MAX_DURATION_MINUTES, $minutes));
    }

    public function availableCount(SubjectCode $subjectCode, array $materialIds): int
    {
        $materialIds = array_values(array_filter(array_map('intval', $materialIds)));

        if ($materialIds === []) {
            return 0;
        }

        return $this->baseQuery($subjectCode, $materialIds)->count();
    }

    public function weakQuestionCount(User $user, SubjectCode $subjectCode, array $materialIds): int
    {
        return count($this->wrongQuestionIdsForUser($user->id, $subjectCode, $materialIds));
    }

    /** @return list<int> */
    private function resolveMaterialIds(DrillConfig $config): array
    {
        if ($config->materialIds !== []) {
            return Material::query()
                ->whereIn('id', $config->materialIds)
                ->whereHas('subject', fn ($query) => $query->where('code', $config->subjectCode))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return Material::query()
            ->whereHas('subject', fn ($query) => $query->where('code', $config->subjectCode))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function baseQuery(SubjectCode $subjectCode, array $materialIds)
    {
        return Question::query()
            ->where('is_active', true)
            ->whereIn('material_id', $materialIds)
            ->whereHas('subject', fn ($query) => $query->where('code', $subjectCode));
    }

    /**
     * @return list<int>
     */
    private function selectWeakQuestions(User $user, SubjectCode $subjectCode, array $materialIds, int $limit): array
    {
        $weakIds = $this->wrongQuestionIdsForUser($user->id, $subjectCode, $materialIds);

        if ($weakIds === []) {
            return $this->selectRandomQuestions($this->baseQuery($subjectCode, $materialIds), $limit);
        }

        shuffle($weakIds);

        if (count($weakIds) >= $limit) {
            return array_slice($weakIds, 0, $limit);
        }

        $remaining = $limit - count($weakIds);
        $randomIds = $this->selectRandomQuestions(
            $this->baseQuery($subjectCode, $materialIds)->whereNotIn('id', $weakIds),
            $remaining,
        );

        return array_values(array_unique(array_merge($weakIds, $randomIds)));
    }

    /**
     * @return list<int>
     */
    private function selectMixedQuestions(
        User $user,
        SubjectCode $subjectCode,
        array $materialIds,
        $pool,
        int $limit,
    ): array {
        $weakCount = (int) max(1, floor($limit * self::MIXED_WEAK_RATIO));
        $weakIds = array_slice(
            $this->wrongQuestionIdsForUser($user->id, $subjectCode, $materialIds),
            0,
            $weakCount,
        );

        shuffle($weakIds);
        $weakIds = array_slice($weakIds, 0, min($weakCount, count($weakIds)));

        $remaining = $limit - count($weakIds);

        if ($remaining <= 0) {
            return array_slice($weakIds, 0, $limit);
        }

        $randomIds = $this->selectRandomQuestions(
            $pool->whereNotIn('id', $weakIds),
            $remaining,
        );

        $combined = array_values(array_unique(array_merge($weakIds, $randomIds)));

        if (count($combined) < $limit) {
            $extra = $this->selectRandomQuestions(
                $pool->whereNotIn('id', $combined),
                $limit - count($combined),
            );
            $combined = array_values(array_unique(array_merge($combined, $extra)));
        }

        shuffle($combined);

        return array_slice($combined, 0, $limit);
    }

    /**
     * @return list<int>
     */
    private function selectRandomQuestions($query, int $limit): array
    {
        return $query
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function wrongQuestionIdsForUser(int $userId, SubjectCode $subjectCode, array $materialIds): array
    {
        return ExamAnswer::query()
            ->whereHas('attempt', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('attempt_type', ExamAttemptType::Full)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired]))
            ->whereHas('question', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('material_id', $materialIds)
                ->whereHas('subject', fn ($subjectQuery) => $subjectQuery->where('code', $subjectCode)))
            ->with(['selectedOption', 'question.options'])
            ->get()
            ->filter(fn (ExamAnswer $answer) => ! $answer->reviewOutcome()->isPositive())
            ->pluck('question_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
