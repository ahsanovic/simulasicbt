<?php

namespace App\Livewire\Concerns;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\LearningPlanTaskCategory;
use App\Models\AiRecommendation;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningPlan;
use App\Services\DeepSeekRecommendationService;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\FlashcardService;
use App\Services\LearningPlanService;
use Illuminate\Validation\ValidationException;
use Throwable;

trait InteractsWithAiReadinessReport
{
    use InteractsWithStressTestModal;

    public string $variant = 'sidebar';

    public ?string $focusHighlight = null;

    public bool $isLoading = false;

    public bool $isGenerated = false;

    public bool $needsRefresh = false;

    public ?string $error = null;

    public ?string $recommendation = null;

    /** @var array<string, mixed> */
    public array $weaknessStats = [];

    protected function initializeAiReadinessReport(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->weaknessStats = $weaknessAnalysis->getStatsForUser((int) auth()->id());
        $this->loadStoredRecommendation($recommendationService);
    }

    public function generateRecommendation(
        DeepSeekRecommendationService $recommendationService,
        ExamWeaknessAnalysisService $weaknessAnalysis,
    ): void {
        $this->error = null;
        $this->isLoading = true;

        try {
            $userId = (int) auth()->id();
            $this->weaknessStats = $weaknessAnalysis->getStatsForUser($userId);

            if (($this->weaknessStats['total_simulations'] ?? 0) === 0) {
                $this->error = 'Selesaikan simulasi pertama untuk membuka analisis.';
            } elseif ($recommendationService->hasValidRecommendation($userId) && ! $this->needsRefresh) {
                $stored = $recommendationService->getStoredRecommendation($userId);
                $this->applyRecommendation($stored?->recommendation_text, $stored?->weakness_stats ?? $this->weaknessStats);
            } else {
                $result = $recommendationService->generateForUser(auth()->user());
                $this->applyRecommendation($result->recommendation_text, $result->weakness_stats ?? $this->weaknessStats);
            }
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
            $this->isGenerated = false;
        } finally {
            $this->isLoading = false;
        }

        if ($this->isGenerated) {
            app(LearningPlanService::class)->completeMatchingTasks(
                auth()->user(),
                LearningPlanTaskCategory::Evaluasi,
            );
        }
    }

    private function loadStoredRecommendation(
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $userId = (int) auth()->id();

        if (($this->weaknessStats['total_simulations'] ?? 0) === 0) {
            return;
        }

        $stored = AiRecommendation::query()->where('user_id', $userId)->first();

        if (! $stored) {
            return;
        }

        $latestAttemptAt = $this->weaknessStats['latest_attempt_at'] ?? null;
        $isValid = $latestAttemptAt !== null
            && $stored->latest_attempt_at?->toDateTimeString() >= $latestAttemptAt;

        if ($isValid) {
            $this->applyRecommendation($stored->recommendation_text, $stored->weakness_stats ?? $this->weaknessStats);

            return;
        }

        $this->needsRefresh = true;
    }

    /** @param  array<string, mixed>  $stats */
    private function applyRecommendation(?string $text, array $stats): void
    {
        if (! filled($text)) {
            return;
        }

        $this->recommendation = $text;
        $this->weaknessStats = $stats;
        $this->isGenerated = true;
        $this->needsRefresh = false;
        $this->error = null;
        $this->dispatch('readiness-chart-updated', stats: $stats);
    }

    public function repeatSimulation(): void
    {
        $exam = $this->resolveRepeatExam();

        if (! $exam) {
            session()->flash('error', 'Tidak ada simulasi yang tersedia saat ini.');
            $this->redirect(route('peserta.dashboard'), navigate: true);

            return;
        }

        $existingAttempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($existingAttempt && $existingAttempt->isActive()) {
            $this->redirect(route('peserta.exam.room', $exam), navigate: true);

            return;
        }

        $this->promptStressTestOrBeginExam($exam);
    }

    public function seedWeakMaterialsToFlashcard(FlashcardService $flashcardService): void
    {
        $result = $flashcardService->seedFromWeakMaterials(auth()->user());

        if ($result['saved'] === 0) {
            session()->flash('warning', $result['preview'] === 0
                ? 'Belum ada soal dari materi lemah yang bisa disimpan.'
                : 'Semua soal materi lemah sudah ada di Kartu Sakti Anda.');

            return;
        }

        session()->flash('success', "{$result['saved']} kartu dari materi lemah disimpan ke Kartu Sakti.");
    }

    public function generatePlanFromEvaluation(LearningPlanService $learningPlanService): void
    {
        $stats = $this->weaknessStats;
        $availability = $learningPlanService->aiGenerationAvailability(auth()->user(), $stats);

        if ($availability['status'] === 'already_generated' && $availability['existing_plan']) {
            session()->flash('info', $availability['message']);
            $this->redirect(
                route('peserta.rencana-belajar.index', ['plan' => $availability['existing_plan']->id]),
                navigate: true,
            );

            return;
        }

        if ($availability['status'] === 'no_simulation') {
            session()->flash('error', $availability['message']);

            return;
        }

        try {
            $plan = $learningPlanService->generateFromWeaknessStats(auth()->user(), $stats);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Tidak bisa membuat rencana belajar.';
            session()->flash('error', $message);

            return;
        }

        session()->flash('success', "Rencana \"{$plan->title}\" berhasil dibuat otomatis dari hasil evaluasi.");
        $this->redirect(route('peserta.rencana-belajar.index', ['plan' => $plan->id]), navigate: true);
    }

    /**
     * @return array{
     *     status: 'no_simulation'|'max_plans'|'already_generated'|'available',
     *     message: string,
     *     existing_plan: ?LearningPlan,
     *     snapshot_hash: ?string,
     * }
     */
    protected function aiPlanGenerationState(): array
    {
        return app(LearningPlanService::class)->aiGenerationAvailability(
            auth()->user(),
            $this->weaknessStats,
        );
    }

    /** @return array{preview: int, available: int, skipped: int} */
    public function getWeakSeedPreviewProperty(): array
    {
        return app(FlashcardService::class)->previewWeakMaterialSeed(auth()->user());
    }

    protected function resolveRepeatExam(): ?Exam
    {
        $userId = (int) auth()->id();

        $lastAttempt = ExamAttempt::query()
            ->with('exam')
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::Submitted)
            ->latest('submitted_at')
            ->latest('created_at')
            ->first();

        $exam = $lastAttempt?->exam;

        if ($exam?->isAvailable()) {
            return $exam;
        }

        return Exam::query()
            ->where('status', ExamStatus::Published)
            ->latest()
            ->get()
            ->first(fn (Exam $candidate) => $candidate->isAvailable());
    }
}
