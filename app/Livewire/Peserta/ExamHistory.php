<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamHistoryFilter;
use App\Enums\TestimonialFeatureTag;
use App\Livewire\Concerns\InteractsWithAiReadinessReport;
use App\Models\ExamAttempt;
use App\Services\DeepSeekRecommendationService;
use App\Services\ExamService;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\FlashcardService;
use App\Services\GamificationService;
use App\Services\TestimonialService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
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

    public bool $showTestimonialGate = false;

    /** True = blocking gate (must submit once); false = optional "beri testimoni lagi". */
    public bool $testimonialRequired = false;

    /** Remedial-unlock modal deferred until the testimonial gate is satisfied. */
    public bool $pendingRemedialUnlock = false;

    public ?ExamAttempt $resultAttempt = null;

    public string $typeFilter = 'all';

    public string $targetInstansi = '';

    public string $story = '';

    public string $turningPoint = '';

    /** @var list<string> */
    public array $selectedTags = [];

    public bool $isAnonymous = false;

    public int $rating = 0;

    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
        TestimonialService $testimonialService,
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
        }

        // Required testimonial gate: as long as the peserta has finished at least
        // one exam but hasn't left a testimonial yet, block the history page (and
        // its scores) behind the testimonial popup on every visit. shouldPromptUser
        // returns false the moment a testimonial exists, so it only ever asks once.
        if ($testimonialService->shouldPromptUser(auth()->user())) {
            $this->prefillTestimonialGate();
            $this->showTestimonialGate = true;
            $this->testimonialRequired = true;
            // Hold these back until the testimonial is submitted.
            $this->pendingRemedialUnlock = (bool) session()->pull('show_remedial_unlock_modal');

            return;
        }

        if ($this->resultAttempt) {
            $this->showResultModal = true;
        }

        if (session()->pull('show_remedial_unlock_modal') && ! $this->showResultModal) {
            $this->showRemedialUnlockModal = true;
        }
    }

    public function closeResultModal(): void
    {
        $this->showResultModal = false;
        $this->resultAttempt = null;

        if (session()->pull('show_remedial_unlock_modal') || $this->pendingRemedialUnlock) {
            $this->pendingRemedialUnlock = false;
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

    public function closeRemedialUnlockModal(): void
    {
        $this->showRemedialUnlockModal = false;
    }

    /**
     * Optional "beri testimoni lagi": opens the testimonial form (dismissible),
     * pre-filled with the peserta's existing testimonial so they update it rather
     * than creating a duplicate. Never blocks — the score stays accessible.
     */
    public function openTestimonialForm(TestimonialService $testimonialService): void
    {
        $existing = $testimonialService->userTestimonial(auth()->user());

        if ($existing) {
            $this->targetInstansi = $existing->target_instansi;
            $this->story = $existing->story;
            $this->turningPoint = $existing->turning_point ?? '';
            $this->selectedTags = $existing->feature_tags ?? [];
            $this->isAnonymous = (bool) $existing->is_anonymous;
            $this->rating = (int) ($existing->rating ?? 0);
        } else {
            $this->prefillTestimonialGate();
        }

        $this->resetValidation();
        $this->testimonialRequired = false;
        $this->showResultModal = false;
        $this->showTestimonialGate = true;
    }

    public function closeTestimonialGate(): void
    {
        // The mandatory gate can't be dismissed — a testimonial must be given once.
        if ($this->testimonialRequired) {
            return;
        }

        $this->showTestimonialGate = false;
        $this->resetValidation();

        // Return to the result popup if it was open behind the optional form.
        if ($this->resultAttempt !== null) {
            $this->showResultModal = true;
        }
    }

    public function toggleTestimonialTag(string $tag): void
    {
        if (TestimonialFeatureTag::tryFrom($tag) === null) {
            return;
        }

        if (in_array($tag, $this->selectedTags, true)) {
            $this->selectedTags = array_values(array_filter(
                $this->selectedTags,
                fn (string $value) => $value !== $tag,
            ));

            return;
        }

        $this->selectedTags[] = $tag;
    }

    public function submitTestimonialGate(TestimonialService $testimonialService): void
    {
        $this->ensureTestimonialSubmitIsNotRateLimited();

        $this->targetInstansi = sanitize_testimonial_text($this->targetInstansi);
        $this->story = sanitize_testimonial_text($this->story, multiline: true);
        $this->turningPoint = sanitize_testimonial_text($this->turningPoint, multiline: true);
        $this->selectedTags = array_values(array_filter(
            $this->selectedTags,
            fn (string $tag) => TestimonialFeatureTag::tryFrom($tag) !== null,
        ));

        $this->validate([
            'targetInstansi' => ['required', 'string', 'min:3', 'max:120'],
            'story' => ['required', 'string', 'min:20', 'max:2000'],
            'rating' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'turningPoint' => ['nullable', 'string', 'max:1000'],
            'selectedTags' => ['required', 'array', 'min:1', 'max:8'],
            'selectedTags.*' => ['string', Rule::enum(TestimonialFeatureTag::class)],
            'isAnonymous' => ['boolean'],
        ], [
            'targetInstansi.required' => 'Ceritakan formasi dan instansi target Anda.',
            'story.required' => 'Cerita pengalaman belajar wajib diisi.',
            'story.min' => 'Cerita minimal 20 karakter agar lebih bermakna.',
            'rating.required' => 'Berikan rating 1-5 bintang untuk pengalaman Anda.',
            'rating.in' => 'Berikan rating 1-5 bintang untuk pengalaman Anda.',
            'selectedTags.required' => 'Pilih minimal satu fitur andalan.',
        ]);

        $testimonialService->submit(auth()->user(), [
            'target_instansi' => $this->targetInstansi,
            'story' => $this->story,
            'rating' => $this->rating,
            'turning_point' => $this->turningPoint,
            'feature_tags' => $this->selectedTags,
            'is_anonymous' => $this->isAnonymous,
        ]);

        RateLimiter::clear($this->testimonialSubmitThrottleKey());

        $wasRequired = $this->testimonialRequired;
        $this->showTestimonialGate = false;
        $this->testimonialRequired = false;

        if ($wasRequired) {
            // First-time mandatory gate: reveal whatever was held back behind it.
            if ($this->resultAttempt !== null) {
                $this->showResultModal = true;
            } elseif ($this->pendingRemedialUnlock) {
                $this->pendingRemedialUnlock = false;
                $this->showRemedialUnlockModal = true;
            }

            session()->flash('success', 'Testimoni berhasil dikirim! Nilai Anda sudah bisa dilihat.');

            return;
        }

        // Optional "beri testimoni lagi": just return to the result if it was open.
        if ($this->resultAttempt !== null) {
            $this->showResultModal = true;
        }

        session()->flash('success', 'Terima kasih! Testimoni Anda berhasil diperbarui.');
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

    public function render(GamificationService $gamificationService, TestimonialService $testimonialService)
    {
        $filter = ExamHistoryFilter::from($this->typeFilter);

        $attempts = ExamAttempt::query()
            ->with(['exam', 'event:id,name', 'answers.question', 'answers.selectedOption'])
            ->where('user_id', auth()->id())
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->forHistoryFilter($filter)
            ->latest('submitted_at')
            ->latest('created_at')
            ->paginate(5);

        $submittedAttempts = ExamAttempt::query()
            ->official()
            ->where('user_id', auth()->id())
            ->whereNull('duel_session_id')
            ->whereNull('event_id')
            ->where('status', ExamAttemptStatus::Submitted)
            ->get(['score_twk', 'score_tiu', 'score_tkp', 'total_score']);

        $totalXp = $gamificationService->totalXp(auth()->user());
        $formationName = auth()->user()->formation?->name;

        $stats = [
            'total' => $submittedAttempts->count(),
            'average' => (int) round((float) ($submittedAttempts->avg('total_score') ?? 0)),
            'passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_attempt_passes(
                    $attempt->score_twk,
                    $attempt->score_tiu,
                    $attempt->score_tkp,
                    $attempt->total_score,
                ))
                ->count(),
        ];

        return view('livewire.peserta.exam-history', [
            'attempts' => $attempts,
            'stats' => $stats,
            'passingGrades' => exam_passing_grades(),
            'scoreMax' => exam_score_max(),
            'repeatExam' => $this->resolveRepeatExam(),
            'totalXp' => $totalXp,
            'remedialUnlock' => $gamificationService->remedialUnlockProgress($totalXp),
            'formationName' => $formationName,
            'typeFilters' => ExamHistoryFilter::options(),
            'activeFilter' => $filter,
            'featureTagOptions' => TestimonialFeatureTag::cases(),
            'userHasTestimonial' => $testimonialService->userTestimonial(auth()->user()) !== null,
        ]);
    }

    private function prefillTestimonialGate(): void
    {
        $user = auth()->user();

        $this->targetInstansi = $user->formation?->name
            ?: $user->instansi?->nama
            ?: '';
        $this->selectedTags = [TestimonialFeatureTag::SimulasiCBT->value];
        $this->rating = 0;
    }

    private function ensureTestimonialSubmitIsNotRateLimited(): void
    {
        $key = $this->testimonialSubmitThrottleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'story' => 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    private function testimonialSubmitThrottleKey(): string
    {
        return 'testimonial-submit:'.auth()->id();
    }
}
