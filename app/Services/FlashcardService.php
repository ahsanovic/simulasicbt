<?php

namespace App\Services;

use App\Enums\DailyActivityType;
use App\Enums\ExamAttemptStatus;
use App\Enums\FlashcardRating;
use App\Enums\FlashcardSourceType;
use App\Enums\LearningPlanTaskCategory;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Flashcard;
use App\Models\FlashcardReviewSession;
use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class FlashcardService
{
    public const DAILY_REVIEW_LIMIT = 10;

    public function __construct(
        private readonly SpacedRepetitionService $spacedRepetition,
        private readonly ExamWeaknessAnalysisService $weaknessAnalysis,
    ) {}

    public function activeCount(User $user): int
    {
        return Flashcard::query()
            ->where('user_id', $user->id)
            ->count();
    }

    public function dueCount(User $user): int
    {
        return Flashcard::query()
            ->where('user_id', $user->id)
            ->where('next_review_at', '<=', now())
            ->count();
    }

    /** @return EloquentCollection<int, Flashcard> */
    public function dueCards(User $user, int $limit = self::DAILY_REVIEW_LIMIT): EloquentCollection
    {
        return Flashcard::query()
            ->where('user_id', $user->id)
            ->where('next_review_at', '<=', now())
            ->with('material')
            ->orderBy('next_review_at')
            ->limit($limit)
            ->get();
    }

    /** @return EloquentCollection<int, Flashcard> */
    public function mostForgotten(User $user, int $limit = 5): EloquentCollection
    {
        return Flashcard::query()
            ->where('user_id', $user->id)
            ->where('forget_count', '>', 0)
            ->with('material')
            ->orderByDesc('forget_count')
            ->orderBy('next_review_at')
            ->limit($limit)
            ->get();
    }

    public function isSaved(User $user, FlashcardSourceType $sourceType, int $sourceId): bool
    {
        return Flashcard::query()
            ->where('user_id', $user->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    /** @return list<int> */
    public function savedQuestionIds(User $user, array $questionIds = []): array
    {
        $query = Flashcard::query()
            ->where('user_id', $user->id)
            ->where('source_type', FlashcardSourceType::Question);

        if ($questionIds !== []) {
            $query->whereIn('source_id', $questionIds);
        }

        return $query->pluck('source_id')->map(fn ($id) => (int) $id)->all();
    }

    public function saveFromQuestion(User $user, Question $question): Flashcard
    {
        $question = Question::query()
            ->with(['subject', 'material', 'options'])
            ->findOrFail($question->id);

        if ($this->isSaved($user, FlashcardSourceType::Question, $question->id)) {
            throw ValidationException::withMessages([
                'flashcard' => 'Soal ini sudah ada di Kartu Sakti Anda.',
            ]);
        }

        $this->assertCanAddCard($user);

        return Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => $question->id,
            'front' => $question->content,
            'back' => $this->buildQuestionBack($question),
            'subject_code' => $question->subject->code,
            'material_id' => $question->material_id,
            'interval_days' => SpacedRepetitionService::INTERVALS[0],
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now(),
        ]);
    }

    public function saveFromMaterial(User $user, Material $material): Flashcard
    {
        $material->loadMissing(['subject', 'cheatSheet']);

        if (! $material->cheatSheet?->isPublished()) {
            throw ValidationException::withMessages([
                'flashcard' => 'Cheat-sheet materi ini belum tersedia.',
            ]);
        }

        if ($this->isSaved($user, FlashcardSourceType::CheatSheet, $material->id)) {
            throw ValidationException::withMessages([
                'flashcard' => 'Materi ini sudah ada di Kartu Sakti Anda.',
            ]);
        }

        $this->assertCanAddCard($user);

        return Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::CheatSheet,
            'source_id' => $material->id,
            'front' => $this->buildCheatSheetFront($material),
            'back' => format_cheat_sheet_content($material->cheatSheet->content),
            'subject_code' => $material->subject->code,
            'material_id' => $material->id,
            'interval_days' => SpacedRepetitionService::INTERVALS[0],
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now(),
        ]);
    }

    /**
     * @return array{saved: int, skipped: int, total_candidates: int}
     */
    public function saveWrongAnswersFromAttempt(User $user, ExamAttempt $attempt): array
    {
        $attempt->loadMissing([
            'answers.question.subject',
            'answers.question.material',
            'answers.question.options',
            'answers.selectedOption',
        ]);

        $saved = 0;
        $skipped = 0;
        $candidates = 0;

        foreach ($attempt->answers as $answer) {
            if ($answer->reviewOutcome()->isPositive() || ! $answer->question) {
                continue;
            }

            $candidates++;

            if ($this->isSaved($user, FlashcardSourceType::Question, $answer->question_id)) {
                $skipped++;

                continue;
            }

            if ($this->activeCount($user) >= Flashcard::MAX_ACTIVE_CARDS) {
                break;
            }

            $this->saveFromQuestion($user, $answer->question);
            $saved++;
        }

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'total_candidates' => $candidates,
        ];
    }

    /**
     * @return array{preview: int, available: int, skipped: int}
     */
    public function previewWeakMaterialSeed(User $user, int $limit = 20): array
    {
        $questionIds = $this->weakMaterialQuestionIds($user, $limit);
        $savedIds = $this->savedQuestionIds($user, $questionIds);

        return [
            'preview' => count($questionIds),
            'available' => count(array_diff($questionIds, $savedIds)),
            'skipped' => count(array_intersect($questionIds, $savedIds)),
        ];
    }

    /**
     * @return array{saved: int, skipped: int, preview: int}
     */
    public function seedFromWeakMaterials(User $user, int $limit = 20): array
    {
        $questionIds = $this->weakMaterialQuestionIds($user, $limit);
        $saved = 0;
        $skipped = 0;

        $questions = Question::query()
            ->whereIn('id', $questionIds)
            ->with(['subject', 'material', 'options'])
            ->get()
            ->keyBy('id');

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);

            if (! $question) {
                continue;
            }

            if ($this->isSaved($user, FlashcardSourceType::Question, $question->id)) {
                $skipped++;

                continue;
            }

            if ($this->activeCount($user) >= Flashcard::MAX_ACTIVE_CARDS) {
                break;
            }

            $this->saveFromQuestion($user, $question);
            $saved++;
        }

        return [
            'saved' => $saved,
            'skipped' => $skipped,
            'preview' => count($questionIds),
        ];
    }

    public function rateCard(Flashcard $card, FlashcardRating $rating): Flashcard
    {
        return $this->spacedRepetition->applyRating($card, $rating);
    }

    public function recordSession(User $user, int $cardCount, int $durationSeconds): FlashcardReviewSession
    {
        $dailyStreak = app(DailyStreakService::class);
        $dailyStreak->logActivity($user, DailyActivityType::Flashcard);
        $xpEarned = $dailyStreak->applyMultiplier($cardCount, $dailyStreak->dailyStreak($user));

        $session = FlashcardReviewSession::query()->create([
            'user_id' => $user->id,
            'card_count' => $cardCount,
            'xp_earned' => $xpEarned,
            'duration_seconds' => max(0, $durationSeconds),
            'completed_at' => now(),
        ]);

        app(LearningPlanService::class)->completeMatchingTasks($user, LearningPlanTaskCategory::KartuSakti);

        return $session;
    }

    /** @return list<int> */
    private function weakMaterialQuestionIds(User $user, int $limit): array
    {
        $stats = $this->weaknessAnalysis->getStatsForUser($user->id);
        $weakMaterialIds = collect($stats['materials'] ?? [])
            ->filter(fn (array $material) => in_array($material['status'], ['kritis', 'cukup'], true))
            ->sortBy('percentage')
            ->pluck('material_id')
            ->filter()
            ->values();

        if ($weakMaterialIds->isEmpty()) {
            return [];
        }

        $wrongQuestionIds = ExamAnswer::query()
            ->whereHas('attempt', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired]))
            ->whereHas('question', fn ($query) => $query->whereIn('material_id', $weakMaterialIds))
            ->with(['question', 'selectedOption'])
            ->get()
            ->filter(fn (ExamAnswer $answer) => ! $answer->reviewOutcome()->isPositive() && $answer->question)
            ->pluck('question_id')
            ->unique()
            ->values();

        return $wrongQuestionIds->take($limit)->map(fn ($id) => (int) $id)->all();
    }

    private function assertCanAddCard(User $user): void
    {
        if ($this->activeCount($user) >= Flashcard::MAX_ACTIVE_CARDS) {
            throw ValidationException::withMessages([
                'flashcard' => 'Batas maksimal '.Flashcard::MAX_ACTIVE_CARDS.' kartu aktif tercapai. Review kartu yang ada terlebih dahulu.',
            ]);
        }
    }

    private function buildQuestionBack(Question $question): string
    {
        $keyOption = $question->options->first(
            fn ($option) => $question->isKeyOption($option),
        );

        $parts = [];

        if ($keyOption) {
            $parts[] = '<p><strong>Kunci: '.e($keyOption->label).'</strong></p>';

            if ($keyOption->isImage()) {
                $parts[] = '<img src="'.e($keyOption->imageUrl()).'" alt="Kunci jawaban" class="max-h-48 rounded-lg object-contain">';
            } elseif (filled($keyOption->content)) {
                $parts[] = $keyOption->content;
            }
        }

        if (filled($question->explanation)) {
            $parts[] = '<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">'.$question->explanation.'</div>';
        }

        return implode('', $parts);
    }

    private function buildCheatSheetFront(Material $material): string
    {
        $subjectLabel = e($material->subject->code->label());
        $materialName = e($material->name);

        return <<<HTML
<p><strong>{$materialName}</strong> <span class="text-slate-500">({$subjectLabel})</span></p>
<p>Apa poin-poin kunci materi ini?</p>
HTML;
    }
}
