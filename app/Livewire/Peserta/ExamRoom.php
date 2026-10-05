<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\HelpItem;
use App\Livewire\Concerns\EnforcesExamDeadline;
use App\Livewire\Concerns\VersionsExamAnswers;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\ExamPsychologyTelemetryService;
use App\Services\ExamService;
use App\Services\ExamStressResilienceService;
use App\Services\HelpItemService;
use App\Support\ExamQuestionCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Ruang Ujian')]
class ExamRoom extends Component
{
    use EnforcesExamDeadline;
    use VersionsExamAnswers;

    #[Locked]
    public int $examId;

    #[Locked]
    public string $examTitle;

    #[Locked]
    public int $attemptId;

    #[Locked]
    public int $attemptExpiresAt;

    #[Locked]
    public bool $isRemedial = false;

    #[Locked]
    public bool $isDrill = false;

    #[Locked]
    public bool $isDuel = false;

    #[Locked]
    public bool $helpItemsEnabled = false;

    #[Locked]
    public bool $isModeUjian = false;

    /** Event attempts can get extra time from the livescore board, so poll more often. */
    #[Locked]
    public bool $isEventAttempt = false;

    #[Locked]
    public bool $stressTestEnabled = false;

    #[Locked]
    public int $examDurationMinutes = 0;

    /** @var array{red_zone_triggers: int, red_zone_questions: list<int>} */
    #[Locked]
    public array $stressTestTelemetry = [
        'red_zone_triggers' => 0,
        'red_zone_questions' => [],
    ];

    #[Locked]
    public bool $needsNameConfirmation = false;

    #[Locked]
    public ?string $eventName = null;

    #[Locked]
    public ?string $eventSessionName = null;

    #[Locked]
    public int $questionCount = 0;

    public string $displayNameInput = '';

    #[Locked]
    public int $currentIndex = 0;

    /** @var list<array{id: int, sort_order: int, question_id: int, selected_option_id: ?int, is_marked: bool}> */
    #[Locked]
    public array $answerStates = [];

    /** @var list<int> */
    #[Locked]
    public array $currentOptionIds = [];

    /**
     * The option highlighted on screen. Bound with a deferred wire:model, so
     * picking an answer is instant in the browser and only travels to the
     * server together with "Simpan & Lanjutkan" / navigation.
     */
    public ?int $selectedOptionId = null;

    /** Saved option of the current question, for browser-side button states. */
    #[Locked]
    public ?int $savedOptionId = null;

    /** @var array<string, int> */
    #[Locked]
    public array $questionDurations = [];

    /** @var array<string, array{first_option_id: ?int, change_count: int, last_change_remaining_seconds: ?int}> */
    #[Locked]
    public array $answerBehavior = [];

    #[Locked]
    public ?int $questionTimerStartedAt = null;

    #[Locked]
    public bool $showLastQuestionModal = false;

    #[Locked]
    public bool $skipTrackerActive = false;

    /** @var array<string, list<int>> */
    #[Locked]
    public array $fiftyFiftyEliminated = [];

    /** @var array<string, int> */
    #[Locked]
    public array $inventory = [];

    public function mount(Exam $exam, HelpItemService $helpItemService): void
    {
        $attempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->with([
                'event:id,name,is_mode_ujian',
                'eventSession:id,name',
                'answers' => fn ($query) => $query->select(
                    'id',
                    'exam_attempt_id',
                    'question_id',
                    'sort_order',
                    'selected_option_id',
                    'answer_version',
                    'is_marked',
                ),
            ])
            ->firstOrFail();

        if (! $attempt->isActive()) {
            if ($attempt->event_id !== null) {
                // Event exam whose time ran out while the peserta was away
                // (refresh, disconnect, anti-cheat logout): score the saved
                // answers like the livescore does, instead of dropping them.
                app(ExamService::class)->finalizeExpiredAttempts([$attempt]);
                session()->flash('error', 'Waktu ujian habis. Jawaban yang sudah tersimpan telah dikumpulkan.');
                $this->redirect($this->resultUrl($attempt->fresh(['event'])), navigate: false);

                return;
            }

            $attempt->update(['status' => ExamAttemptStatus::Expired]);
            $this->redirect(route('peserta.dashboard'), navigate: true);

            return;
        }

        $this->examId = $exam->id;
        $this->examTitle = $attempt->isDrill() ? $attempt->displayTitle() : $exam->title;
        $this->attemptId = $attempt->id;
        $this->examDurationMinutes = (int) $exam->duration_minutes;
        $this->attemptExpiresAt = $attempt->expires_at->timestamp;

        // Peserta confirms/edits their name (Google login names are often
        // inconsistent) before the questions and timer are shown to them.
        // Only asked for event/offline attempts, since that's where the name
        // is later printed on the certificate and shown on the live display.
        // Only asked once per attempt — skipped entirely on resume/refresh.
        if ($attempt->event_id !== null && $attempt->needsNameConfirmation()) {
            $this->needsNameConfirmation = true;
            $this->displayNameInput = (string) auth()->user()->name;
            $this->eventName = $attempt->event?->name;
            $this->eventSessionName = $attempt->eventSession?->name;
            $this->questionCount = $attempt->answers->count();

            return;
        }
        $this->isRemedial = $attempt->isRemedial();
        $this->isDrill = $attempt->isDrill();
        $this->isDuel = $attempt->isDuelAttempt();
        $this->isModeUjian = (bool) ($attempt->event?->is_mode_ujian ?? false);
        $this->isEventAttempt = $attempt->event_id !== null;
        $this->helpItemsEnabled = $attempt->isFull() && ! $this->isRemedial && ! $this->isDrill && ! $this->isDuel && ! $this->isModeUjian;
        $this->stressTestEnabled = (bool) $attempt->stress_test_enabled;
        $this->answerVersionBase = (int) $attempt->answers->max('answer_version');
        $this->answerStates = $attempt->answers
            ->sortBy(fn (ExamAnswer $answer) => $answer->sort_order ?: 999)
            ->values()
            ->map(fn (ExamAnswer $answer) => [
                'id' => $answer->id,
                'sort_order' => (int) $answer->sort_order,
                'question_id' => $answer->question_id,
                'selected_option_id' => $answer->selected_option_id,
                'is_marked' => (bool) $answer->is_marked,
            ])
            ->all();

        $stored = $attempt->question_duration ?? [];
        $this->questionDurations = collect($stored['by_sort_order'] ?? [])
            ->mapWithKeys(fn ($seconds, $key) => [(string) $key => max(0, (int) $seconds)])
            ->all();

        $this->loadAnswerBehavior($attempt);
        $this->loadStressTestTelemetry($attempt);

        $helpState = $attempt->help_items_state ?? $helpItemService->defaultHelpItemsState();
        $this->skipTrackerActive = (bool) ($helpState['skip_tracker_active'] ?? false);
        $this->fiftyFiftyEliminated = collect($helpState['fifty_fifty'] ?? [])
            ->mapWithKeys(fn (array $optionIds, $sortOrder) => [(string) $sortOrder => array_map('intval', $optionIds)])
            ->all();

        $this->inventory = $helpItemService->inventory(auth()->user());

        // Back after a refresh, lost connection or anti-cheat logout: continue
        // at the first question without a saved answer, not at question 1.
        $this->currentIndex = $this->firstUnansweredIndex();

        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
        $this->dispatch('question-changed', questionNumber: $this->currentIndex + 1);
    }

    public function getAnswersProperty()
    {
        return collect($this->answerStates)->map(fn (array $state) => (object) $state);
    }

    /** The question on screen (content, options, subject), from the shared question cache. */
    #[Computed]
    public function currentQuestion(): ?Question
    {
        $state = $this->currentAnswerState();

        return $state === null ? null : ExamQuestionCache::skd($state['question_id']);
    }

    public function getAnsweredCountProperty(): int
    {
        return collect($this->answerStates)
            ->whereNotNull('selected_option_id')
            ->count();
    }

    public function getUnansweredCountProperty(): int
    {
        return count($this->answerStates) - $this->answeredCount;
    }

    public function getProgressPercentProperty(): int
    {
        if ($this->answerStates === []) {
            return 0;
        }

        return (int) round(($this->answeredCount / count($this->answerStates)) * 100);
    }

    public function getCurrentEliminatedOptionIdsProperty(): array
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return [];
        }

        return $this->fiftyFiftyEliminated[(string) $state['sort_order']] ?? [];
    }

    public function getCanUseFiftyFiftyProperty(): bool
    {
        if (! $this->helpItemsEnabled || ($this->inventory[HelpItem::FiftyFifty->value] ?? 0) < 1) {
            return false;
        }

        $question = $this->currentQuestion;

        if ($question === null) {
            return false;
        }

        $state = $this->currentAnswerState();

        if ($state === null) {
            return false;
        }

        if (isset($this->fiftyFiftyEliminated[(string) $state['sort_order']])) {
            return false;
        }

        return app(HelpItemService::class)->canUseFiftyFifty($question);
    }

    public function activateSkipTracker(HelpItemService $helpItemService): void
    {
        if (! $this->helpItemsEnabled || $this->skipTrackerActive) {
            return;
        }

        try {
            $helpItemService->consume(auth()->user(), HelpItem::SkipTracker);
            $this->skipTrackerActive = true;
            $this->inventory = $helpItemService->inventory(auth()->user());
            $this->persistHelpItemsState();
            session()->flash('success', 'Skip Tracker aktif untuk simulasi ini.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            session()->flash('error', $message ?? 'Gagal mengaktifkan Skip Tracker.');
        }
    }

    public function useFiftyFifty(HelpItemService $helpItemService): void
    {
        if (! $this->canUseFiftyFifty) {
            return;
        }

        $question = $this->currentQuestion;
        $state = $this->currentAnswerState();

        if ($question === null || $state === null) {
            return;
        }

        try {
            $attempt = $this->resolveAttempt();
            $eliminated = $helpItemService->eliminateWrongOptions($attempt, $question);
            $helpItemService->consume(auth()->user(), HelpItem::FiftyFifty);

            $this->fiftyFiftyEliminated[(string) $state['sort_order']] = $eliminated;
            $this->inventory = $helpItemService->inventory(auth()->user());
            $this->persistHelpItemsState();
            session()->flash('success', '50:50 aktif — dua pilihan salah disembunyikan.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            session()->flash('error', $message ?? 'Gagal menggunakan 50:50.');
        }
    }

    public function skipAndMarkQuestion(): void
    {
        if (! $this->helpItemsEnabled || ! $this->skipTrackerActive || ! $this->ensureWithinDeadline()) {
            return;
        }

        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        if (! $state['is_marked']) {
            ExamAnswer::query()
                ->whereKey($state['id'])
                ->where('exam_attempt_id', $this->attemptId)
                ->update(['is_marked' => true]);

            $this->syncMarkedInMemory(true);
        }

        if (! $this->persistCurrentAnswer()) {
            return;
        }

        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();

        $nextIndex = $this->findNextUnansweredIndex($this->currentIndex + 1);

        if ($nextIndex === null) {
            $nextIndex = $this->findNextUnansweredIndex(0, $this->currentIndex);
        }

        if ($nextIndex === null) {
            session()->flash('info', 'Semua soal sudah dijawab.');

            return;
        }

        $this->showLastQuestionModal = false;
        $this->currentIndex = $nextIndex;
        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
        $this->dispatch('question-changed', questionNumber: $this->currentIndex + 1);
    }

    public function goToShop(): void
    {
        $this->redirect(route('peserta.shop.index'), navigate: true);
    }

    public function getRemainingSecondsProperty(): int
    {
        return $this->deadlineRemainingSeconds();
    }

    public function saveAnswer(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        $this->persistCurrentAnswer();
    }

    /**
     * Save the on-screen pick of the current question. Returns false when the
     * save was refused as stale (see VersionsExamAnswers): the room is then
     * being reloaded and the caller must stop instead of moving on.
     */
    private function persistCurrentAnswer(bool $reloadIfStale = true): bool
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return true;
        }

        $optionId = $this->selectedOptionId;

        // The pick arrives straight from the browser (deferred wire:model): it
        // must belong to this question and must not be an option removed by
        // the 50:50 help item, otherwise the saved answer is kept.
        if ($optionId !== null && (
            ! $this->isValidOptionForCurrentQuestion($optionId)
            || in_array($optionId, $this->currentEliminatedOptionIds, true)
        )) {
            $optionId = $state['selected_option_id'];
        }

        $version = $this->incomingAnswerVersion();

        $written = ExamAnswer::query()
            ->whereKey($state['id'])
            ->where('exam_attempt_id', $this->attemptId)
            ->when($version !== null, fn ($query) => $query->where('answer_version', '<', $version))
            ->update([
                'selected_option_id' => $optionId,
                'answered_at' => $optionId ? now() : null,
                ...($version !== null ? ['answer_version' => $version] : []),
            ]);

        if ($version !== null && $written === 0) {
            if ($reloadIfStale) {
                $this->reloadForStaleAnswer('exam_answers', $state['id'], $version);
            }

            return false;
        }

        $this->trackAnswerBehavior($state['selected_option_id'], $optionId);
        $this->syncAnswerInMemory($optionId);

        return true;
    }

    public function toggleMark(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        $newMarked = ! $state['is_marked'];

        ExamAnswer::query()
            ->whereKey($state['id'])
            ->where('exam_attempt_id', $this->attemptId)
            ->update([
                'is_marked' => $newMarked,
            ]);

        $this->syncMarkedInMemory($newMarked);
    }

    public function goToQuestion(int $index): void
    {
        if ($index < 0 || $index >= count($this->answerStates) || ! $this->ensureWithinDeadline()) {
            return;
        }

        // No saveAnswer() here: jumping to another question (navigator or
        // "Sebelumnya") does not commit the current pick. loadCurrentAnswer()
        // resets selectedOptionId to the last saved value, discarding it.
        $this->showLastQuestionModal = false;
        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();
        $this->currentIndex = $index;
        $this->loadCurrentAnswer();
        $this->startQuestionTimer();
        $this->dispatch('question-changed', questionNumber: $this->currentIndex + 1);
    }

    public function previous(): void
    {
        if ($this->currentIndex > 0) {
            $this->goToQuestion($this->currentIndex - 1);
        }
    }

    public function next(): void
    {
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        if (! $this->persistCurrentAnswer()) {
            return;
        }

        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();

        if ($this->currentIndex < count($this->answerStates) - 1) {
            $this->currentIndex++;
            $this->loadCurrentAnswer();
            $this->startQuestionTimer();
            $this->dispatch('question-changed', questionNumber: $this->currentIndex + 1);
        } else {
            $this->showLastQuestionModal = true;
        }
    }

    public function closeLastQuestionModal(): void
    {
        $this->showLastQuestionModal = false;
    }

    public function goBackFromLastQuestionModal(): void
    {
        $this->showLastQuestionModal = false;

        $firstUnansweredIndex = collect($this->answerStates)
            ->search(fn (array $state) => $state['selected_option_id'] === null);

        if ($firstUnansweredIndex !== false) {
            $this->goToQuestion($firstUnansweredIndex);

            return;
        }

        if ($this->currentIndex > 0) {
            $this->goToQuestion(0);
        }
    }

    /**
     * Save the peserta-confirmed name onto this attempt only (does not touch
     * users.name) and reload the component so the exam questions/timer view
     * mounts fresh.
     */
    public function confirmDisplayName(): void
    {
        $name = trim($this->displayNameInput);

        $this->validate([
            'displayNameInput' => ['required', 'string', 'min:3', 'max:191'],
        ], [], ['displayNameInput' => 'Nama']);

        $attempt = ExamAttempt::query()
            ->where('id', $this->attemptId)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->firstOrFail();

        $attempt->update(['display_name' => $name]);

        $this->redirect(route('peserta.exam.room', $this->examId), navigate: true);
    }

    public function submitExam(ExamService $examService): void
    {
        // Past the deadline this closes the attempt like a timeout instead,
        // so a late pick isn't saved and the peserta sees why.
        if (! $this->ensureWithinDeadline()) {
            return;
        }

        $this->showLastQuestionModal = false;

        if (! $this->persistCurrentAnswer()) {
            return;
        }

        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();
        $this->persistHelpItemsState();
        if (! $this->isRemedial && ! $this->isDrill) {
            $this->persistTelemetries();
            $this->persistStressTestAnalysis();
        }
        $attempt = $examService->submitAttempt($this->resolveAttempt(), auth()->user());
        session()->flash('show_result_attempt_id', $attempt->id);
        $this->redirectAfterSubmit($attempt);
    }

    protected function freshDeadlineAttempt(): ?Model
    {
        return ExamAttempt::query()->find($this->attemptId, ['id', 'status', 'expires_at']);
    }

    protected function closeTimedOutAttempt(): string
    {
        // Terminal auto-submit: capture the current on-screen pick, same as a
        // manual submit, so a selected-but-not-yet-saved answer on the active
        // question isn't lost — but only if it was made before time ran out
        // (the browser locks the screen at zero; the grace covers latency).
        // A stale pick is just skipped here: time is up, the attempt closes anyway.
        if ($this->withinAnswerGrace()) {
            $this->persistCurrentAnswer(reloadIfStale: false);
        }

        $this->accumulateCurrentQuestionDuration();
        $this->persistAttemptMetadata();
        $this->persistHelpItemsState();
        if (! $this->isRemedial && ! $this->isDrill) {
            $this->persistTelemetries();
            $this->persistStressTestAnalysis();
        }
        $attempt = app(ExamService::class)->submitAttempt($this->resolveAttempt(), auth()->user());
        session()->flash('show_result_attempt_id', $attempt->id);

        return $this->resultUrl($attempt);
    }

    protected function submittedResultUrl(): string
    {
        return $this->resultUrl($this->resolveAttempt()->loadMissing('event'));
    }

    protected function examRoomUrl(): string
    {
        return route('peserta.exam.room', $this->examId);
    }

    private function redirectAfterSubmit(ExamAttempt $attempt): void
    {
        $this->redirect($this->resultUrl($attempt), navigate: true);
    }

    private function resultUrl(ExamAttempt $attempt): string
    {
        if ($attempt->event?->is_mode_ujian) {
            return route('peserta.mode-ujian.skd-result', $attempt);
        }

        return route('peserta.history', $attempt->isDrill() ? ['filter' => 'drill'] : []);
    }

    private function currentAnswerState(): ?array
    {
        return $this->answerStates[$this->currentIndex] ?? null;
    }

    private function resolveAttempt(): ExamAttempt
    {
        return ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function isValidOptionForCurrentQuestion(int $optionId): bool
    {
        return in_array($optionId, $this->currentOptionIds, true);
    }

    private function syncAnswerInMemory(?int $optionId): void
    {
        if (! isset($this->answerStates[$this->currentIndex])) {
            return;
        }

        $this->answerStates[$this->currentIndex]['selected_option_id'] = $optionId;
        $this->invalidateAnswerComputedProperties();
    }

    private function syncMarkedInMemory(bool $isMarked): void
    {
        if (! isset($this->answerStates[$this->currentIndex])) {
            return;
        }

        $this->answerStates[$this->currentIndex]['is_marked'] = $isMarked;
        $this->invalidateAnswerComputedProperties();
    }

    private function invalidateAnswerComputedProperties(): void
    {
        unset($this->answers, $this->currentQuestion, $this->answeredCount, $this->unansweredCount, $this->progressPercent);
    }

    private function firstUnansweredIndex(): int
    {
        foreach ($this->answerStates as $index => $state) {
            if ($state['selected_option_id'] === null) {
                return $index;
            }
        }

        return 0;
    }

    private function loadCurrentAnswer(): void
    {
        $state = $this->currentAnswerState();

        $this->selectedOptionId = $state['selected_option_id'] ?? null;
        unset($this->currentQuestion);

        $this->currentOptionIds = $this->currentQuestion?->options
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];
    }

    private function startQuestionTimer(): void
    {
        $this->questionTimerStartedAt = now()->timestamp;
    }

    private function accumulateCurrentQuestionDuration(): void
    {
        $state = $this->currentAnswerState();

        if ($state === null || $this->questionTimerStartedAt === null) {
            return;
        }

        $elapsed = max(0, now()->timestamp - $this->questionTimerStartedAt);
        $key = (string) $state['sort_order'];
        $this->questionDurations[$key] = ($this->questionDurations[$key] ?? 0) + $elapsed;
        $this->questionTimerStartedAt = null;
    }

    private function persistAttemptMetadata(): void
    {
        ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->update([
                'question_duration' => ['by_sort_order' => $this->questionDurations],
                'answer_behavior' => ['by_sort_order' => $this->answerBehavior],
            ]);
    }

    private function persistHelpItemsState(): void
    {
        ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->update([
                'help_items_state' => [
                    'skip_tracker_active' => $this->skipTrackerActive,
                    'fifty_fifty' => $this->fiftyFiftyEliminated,
                ],
            ]);
    }

    private function findNextUnansweredIndex(int $start, ?int $endBefore = null): ?int
    {
        $limit = $endBefore ?? count($this->answerStates);

        for ($index = $start; $index < $limit; $index++) {
            if (($this->answerStates[$index]['selected_option_id'] ?? null) === null) {
                return $index;
            }
        }

        return null;
    }

    private function trackAnswerBehavior(?int $previousOptionId, ?int $newOptionId): void
    {
        $state = $this->currentAnswerState();

        if ($state === null) {
            return;
        }

        $key = (string) $state['sort_order'];

        if (! isset($this->answerBehavior[$key])) {
            $this->answerBehavior[$key] = [
                'first_option_id' => $newOptionId,
                'change_count' => 0,
                'last_change_remaining_seconds' => null,
            ];

            return;
        }

        if ($newOptionId === null || $previousOptionId === null || $newOptionId === $previousOptionId) {
            return;
        }

        $this->answerBehavior[$key]['change_count']++;
        $this->answerBehavior[$key]['last_change_remaining_seconds'] = $this->remainingSeconds;
    }

    private function persistTelemetries(): void
    {
        $attempt = ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->with(['answers.question.options', 'answers.selectedOption'])
            ->firstOrFail();

        app(ExamPsychologyTelemetryService::class)->persistForAttempt(
            $attempt,
            $this->questionDurations,
            $this->answerBehavior,
            $this->remainingSeconds,
        );
    }

    public function syncStressTestTelemetry(int $redZoneTriggers, array $redZoneQuestions): void
    {
        if (! $this->stressTestEnabled) {
            return;
        }

        $this->stressTestTelemetry = [
            'red_zone_triggers' => max(0, $redZoneTriggers),
            'red_zone_questions' => array_values(array_unique(array_map('intval', $redZoneQuestions))),
        ];
    }

    private function persistStressTestAnalysis(): void
    {
        if (! $this->stressTestEnabled) {
            return;
        }

        $attempt = ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->with(['answers.question', 'answers.selectedOption', 'exam'])
            ->firstOrFail();

        $analysis = app(ExamStressResilienceService::class)->analyzeAttempt(
            $attempt,
            $this->stressTestTelemetry,
        );

        ExamAttempt::query()
            ->whereKey($this->attemptId)
            ->where('user_id', auth()->id())
            ->update([
                'stress_test_telemetry' => $this->stressTestTelemetry,
                'stress_test_analysis' => $analysis,
            ]);
    }

    private function loadStressTestTelemetry(ExamAttempt $attempt): void
    {
        $stored = $attempt->stress_test_telemetry ?? [];

        $this->stressTestTelemetry = [
            'red_zone_triggers' => max(0, (int) ($stored['red_zone_triggers'] ?? 0)),
            'red_zone_questions' => array_values(array_map('intval', $stored['red_zone_questions'] ?? [])),
        ];
    }

    private function loadAnswerBehavior(ExamAttempt $attempt): void
    {
        $stored = $attempt->answer_behavior ?? [];
        $this->answerBehavior = collect($stored['by_sort_order'] ?? [])
            ->mapWithKeys(fn (array $behavior, $key) => [
                (string) $key => [
                    'first_option_id' => $behavior['first_option_id'] ?? null,
                    'change_count' => max(0, (int) ($behavior['change_count'] ?? 0)),
                    'last_change_remaining_seconds' => $behavior['last_change_remaining_seconds'] ?? null,
                ],
            ])
            ->all();
    }

    public function render()
    {
        $this->savedOptionId = $this->currentAnswerState()['selected_option_id'] ?? null;

        return view('livewire.peserta.exam-room');
    }
}
