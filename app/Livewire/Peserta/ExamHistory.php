<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamHistoryFilter;
use App\Enums\SkdTarget;
use App\Livewire\Concerns\InteractsWithAiReadinessReport;
use App\Models\ExamAttempt;
use App\Services\DeepSeekRecommendationService;
use App\Services\ExamService;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\FlashcardService;
use App\Services\GamificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.peserta', ['activeNav' => 'history', 'showNav' => true])]
#[Title('Riwayat Tes')]
class ExamHistory extends Component
{
    use InteractsWithAiReadinessReport;
    use WithPagination;

    public bool $showResultModal = false;

    public bool $showRemedialUnlockModal = false;

    public ?ExamAttempt $resultAttempt = null;

    public string $typeFilter = 'all';

    public string $skdTargetFilter = 'all';

    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->initializeAiReadinessReport($weaknessAnalysis, $recommendationService);

        $filter = request()->query('filter');

        if (is_string($filter) && ExamHistoryFilter::tryFrom($filter) !== null) {
            $this->typeFilter = $filter;
        }

        $focus = request()->query('focus');

        if ($focus === 'readiness') {
            $this->redirect(route('peserta.evaluasi'), navigate: true);

            return;
        }

        if ($focus === 'time-management') {
            $this->redirect(route('peserta.evaluasi', ['focus' => 'time-management']), navigate: true);

            return;
        }

        if (is_string($focus) && in_array($focus, ['review', 'psychology'], true)) {
            $this->focusHighlight = $focus;
        }

        $resultAttemptId = session()->pull('show_result_attempt_id');

        if ($resultAttemptId) {
            $this->resultAttempt = ExamAttempt::query()
                ->with(['exam', 'event:id,name', 'answers.question', 'answers.selectedOption'])
                ->whereKey($resultAttemptId)
                ->where('user_id', auth()->id())
                ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
                ->first();

            if ($this->resultAttempt) {
                $this->showResultModal = true;
            }
        }

        if (session()->pull('show_remedial_unlock_modal') && ! $this->showResultModal) {
            $this->showRemedialUnlockModal = true;
        }
    }

    public function closeResultModal(): void
    {
        $this->showResultModal = false;
        $this->resultAttempt = null;

        if (session()->pull('show_remedial_unlock_modal')) {
            $this->showRemedialUnlockModal = true;
        }
    }

    public function startRemedial(int $attemptId, ExamService $examService): void
    {
        $parent = ExamAttempt::query()
            ->with(['exam', 'answers.question', 'answers.selectedOption'])
            ->whereKey($attemptId)
            ->where('user_id', auth()->id())
            ->where('attempt_type', ExamAttemptType::Full)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->firstOrFail();

        $existingAttempt = ExamAttempt::query()
            ->where('exam_id', $parent->exam_id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($existingAttempt?->isActive()) {
            $this->redirect(route('peserta.exam.room', $parent->exam), navigate: true);

            return;
        }

        try {
            $examService->startRemedialAttempt($parent, auth()->user());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Tidak bisa memulai ujian remedial.';

            session()->flash('error', $message);

            return;
        }

        $this->redirect(route('peserta.exam.room', $parent->exam), navigate: true);
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSkdTargetFilter(): void
    {
        $this->resetPage();
    }

    public function setSkdTargetFilter(string $value): void
    {
        if ($value === 'all' || SkdTarget::tryFrom($value) !== null) {
            $this->skdTargetFilter = $value;
            $this->resetPage();
        }
    }

    public function closeRemedialUnlockModal(): void
    {
        $this->showRemedialUnlockModal = false;
    }

    public function saveResultWrongToFlashcard(FlashcardService $flashcardService): void
    {
        if (! $this->resultAttempt) {
            return;
        }

        $result = $flashcardService->saveWrongAnswersFromAttempt(auth()->user(), $this->resultAttempt);

        if ($result['saved'] === 0) {
            session()->flash('warning', $result['total_candidates'] === 0
                ? 'Tidak ada soal salah yang bisa disimpan.'
                : 'Semua soal salah sudah ada di Kartu Sakti Anda.');

            return;
        }

        session()->flash('success', "{$result['saved']} soal salah disimpan ke Kartu Sakti.");
    }

    public function getResultAttemptWrongCountProperty(): int
    {
        if (! $this->resultAttempt) {
            return 0;
        }

        $this->resultAttempt->loadMissing([
            'answers.question',
            'answers.selectedOption',
        ]);

        return $this->resultAttempt->answers
            ->filter(fn ($answer) => $answer->question && ! $answer->reviewOutcome()->isPositive())
            ->count();
    }

    public function render(GamificationService $gamificationService)
    {
        $filter = ExamHistoryFilter::from($this->typeFilter);
        $skdTarget = $this->skdTargetFilter === 'all'
            ? null
            : SkdTarget::from($this->skdTargetFilter);

        $attemptsQuery = ExamAttempt::query()
            ->with(['exam', 'event:id,name', 'answers.question', 'answers.selectedOption'])
            ->where('user_id', auth()->id())
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->forHistoryFilter($filter)
            ->when($skdTarget !== null, fn ($query) => $query->where('skd_target', $skdTarget->value))
            ->latest('submitted_at')
            ->latest('created_at');

        $attempts = $attemptsQuery->paginate(5);

        $submittedAttempts = ExamAttempt::query()
            ->official()
            ->where('user_id', auth()->id())
            ->whereNull('duel_session_id')
            ->whereNull('event_id')
            ->where('status', ExamAttemptStatus::Submitted)
            ->when($skdTarget !== null, fn ($query) => $query->where('skd_target', $skdTarget->value))
            ->get(['score_twk', 'score_tiu', 'score_tkp', 'total_score', 'skd_target', 'attempt_type']);

        $totalXp = $gamificationService->totalXp(auth()->user());
        $formationName = auth()->user()->formation?->name;

        $stats = [
            'total' => $submittedAttempts->count(),
            'average' => (int) round((float) ($submittedAttempts->avg('total_score') ?? 0)),
            'passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => $attempt->passes())
                ->count(),
        ];

        return view('livewire.peserta.exam-history', [
            'attempts' => $attempts,
            'stats' => $stats,
            'scoreMax' => exam_score_max(),
            'repeatExam' => $this->resolveRepeatExam(),
            'totalXp' => $totalXp,
            'remedialUnlock' => $gamificationService->remedialUnlockProgress($totalXp),
            'formationName' => $formationName,
            'typeFilters' => ExamHistoryFilter::options(),
            'activeFilter' => $filter,
            'skdTargetFilter' => $this->skdTargetFilter,
            'skdTargetOptions' => SkdTarget::filterOptions(),
            'showAllPassingProfiles' => $skdTarget === null,
            'passingGrades' => $skdTarget !== null ? exam_passing_grades($skdTarget) : null,
            'activeSkdTargetLabel' => $skdTarget?->label(),
        ]);
    }
}
