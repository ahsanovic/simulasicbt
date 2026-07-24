<?php

namespace App\Livewire\Peserta;

use App\Enums\LearningPlanPriority;
use App\Enums\LearningPlanStatus;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\LearningPlanTaskStatus;
use App\Models\LearningPlan;
use App\Models\LearningPlanTask;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\LearningPlanService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'rencana-belajar', 'showNav' => true])]
#[Title('Rencana Belajar')]
class RencanaBelajar extends Component
{
    public string $viewMode = 'board';

    public string $sidebarTab = 'active';

    public ?int $selectedPlanId = null;

    public bool $showPlanModal = false;

    public bool $showTaskModal = false;

    public ?int $editingPlanId = null;

    public ?int $editingTaskId = null;

    public ?int $parentTaskId = null;

    public string $planTitle = '';

    public string $planDescription = '';

    public string $planPriority = 'medium';

    public string $planColor = 'indigo';

    public string $planStartsAt = '';

    public string $planEndsAt = '';

    public string $taskTitle = '';

    public string $taskNotes = '';

    public string $taskCategory = 'materi';

    public string $taskPriority = 'medium';

    public string $taskStatus = 'todo';

    public string $taskScheduledAt = '';

    public int $calendarYear;

    public int $calendarMonth;

    public function mount(LearningPlanService $service): void
    {
        $this->calendarYear = (int) now()->year;
        $this->calendarMonth = (int) now()->month;

        $plans = $service->plansFor(auth()->user());
        $archivedPlans = $service->archivedPlansFor(auth()->user());
        $requestedPlanId = request()->integer('plan');

        if ($requestedPlanId > 0 && $archivedPlans->contains('id', $requestedPlanId)) {
            $this->sidebarTab = 'archive';
            $this->selectedPlanId = $requestedPlanId;
        } elseif ($requestedPlanId > 0 && $plans->contains('id', $requestedPlanId)) {
            $this->sidebarTab = 'active';
            $this->selectedPlanId = $requestedPlanId;
        } else {
            $this->selectedPlanId = $plans->first()?->id;
        }
    }

    public function generateFromEvaluation(
        LearningPlanService $service,
        ExamWeaknessAnalysisService $weaknessAnalysis,
    ): void {
        $user = auth()->user();
        $stats = $weaknessAnalysis->getStatsForUser((int) $user->id);
        $availability = $service->aiGenerationAvailability($user, $stats);

        if ($availability['status'] === 'already_generated' && $availability['existing_plan']) {
            $this->selectedPlanId = $availability['existing_plan']->id;
            $this->viewMode = 'board';
            session()->flash('info', $availability['message']);

            return;
        }

        try {
            $plan = $service->generateFromWeaknessStats($user, $stats);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Tidak bisa membuat rencana belajar.';
            session()->flash('error', $message);

            return;
        }

        $this->selectedPlanId = $plan->id;
        $this->viewMode = 'board';
        session()->flash('success', "Rencana \"{$plan->title}\" berhasil dibuat otomatis dari hasil evaluasi.");
    }

    public function setSidebarTab(string $tab, LearningPlanService $service): void
    {
        if (! in_array($tab, ['active', 'archive'], true)) {
            return;
        }

        $this->sidebarTab = $tab;
        $user = auth()->user();

        $this->selectedPlanId = $tab === 'archive'
            ? $service->archivedPlansFor($user)->first()?->id
            : $service->plansFor($user)->first()?->id;
    }

    public function selectPlan(int $planId): void
    {
        $this->assertOwnsPlan($planId);
        $this->selectedPlanId = $planId;
        $this->viewMode = 'board';
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['board', 'table', 'calendar'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function openCreatePlanModal(): void
    {
        $this->resetPlanForm();
        $this->editingPlanId = null;
        $this->showPlanModal = true;
    }

    public function openEditPlanModal(int $planId): void
    {
        $plan = $this->assertOwnsPlan($planId);

        $this->editingPlanId = $plan->id;
        $this->planTitle = $plan->title;
        $this->planDescription = (string) ($plan->description ?? '');
        $this->planPriority = $plan->priority->value;
        $this->planColor = $plan->color;
        $this->planStartsAt = $plan->starts_at?->format('Y-m-d') ?? '';
        $this->planEndsAt = $plan->ends_at?->format('Y-m-d') ?? '';
        $this->showPlanModal = true;
    }

    public function savePlan(LearningPlanService $service): void
    {
        $validated = $this->validate([
            'planTitle' => ['required', 'string', 'max:120'],
            'planDescription' => ['nullable', 'string', 'max:1000'],
            'planPriority' => ['required', Rule::enum(LearningPlanPriority::class)],
            'planColor' => ['required', Rule::in(array_keys(LearningPlan::COLORS))],
            'planStartsAt' => ['nullable', 'date'],
            'planEndsAt' => ['nullable', 'date', 'after_or_equal:planStartsAt'],
        ], [
            'planTitle.required' => 'Judul rencana wajib diisi.',
            'planTitle.max' => 'Judul rencana maksimal 120 karakter.',
            'planDescription.max' => 'Deskripsi maksimal 1000 karakter.',
            'planPriority.required' => 'Prioritas wajib dipilih.',
            'planPriority.enum' => 'Prioritas tidak valid.',
            'planColor.required' => 'Warna wajib dipilih.',
            'planColor.in' => 'Warna tidak valid.',
            'planStartsAt.date' => 'Tanggal mulai tidak valid.',
            'planEndsAt.date' => 'Tanggal selesai tidak valid.',
            'planEndsAt.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
        ], [
            'planTitle' => 'judul rencana',
            'planDescription' => 'deskripsi',
            'planPriority' => 'prioritas',
            'planColor' => 'warna',
            'planStartsAt' => 'tanggal mulai',
            'planEndsAt' => 'tanggal selesai',
        ]);

        $payload = [
            'title' => $validated['planTitle'],
            'description' => $validated['planDescription'] ?: null,
            'priority' => $validated['planPriority'],
            'color' => $validated['planColor'],
            'starts_at' => $validated['planStartsAt'] ?: null,
            'ends_at' => $validated['planEndsAt'] ?: null,
        ];

        if ($this->editingPlanId) {
            $plan = $this->assertOwnsPlan($this->editingPlanId);
            $service->updatePlan($plan, $payload);
            session()->flash('success', 'Rencana belajar diperbarui.');
        } else {
            $plan = $service->createPlan(auth()->user(), $payload);
            $this->selectedPlanId = $plan->id;
            session()->flash('success', 'Rencana belajar berhasil dibuat.');
        }

        $this->showPlanModal = false;
        $this->resetPlanForm();
    }

    public function completePlan(int $planId, LearningPlanService $service): void
    {
        $plan = $this->assertOwnsPlan($planId);
        $service->updatePlan($plan, ['status' => LearningPlanStatus::Completed->value]);
        session()->flash('success', 'Rencana ditandai selesai.');
    }

    public function archivePlan(int $planId, LearningPlanService $service): void
    {
        $plan = $this->assertOwnsPlan($planId);
        $service->updatePlan($plan, ['status' => LearningPlanStatus::Archived->value]);

        if ($this->selectedPlanId === $planId) {
            $this->sidebarTab = 'active';
            $this->selectedPlanId = $service->plansFor(auth()->user())->first()?->id;
        }

        session()->flash('success', 'Rencana diarsipkan. Buka tab Arsip untuk melihat atau memulihkannya.');
    }

    public function restorePlan(int $planId, LearningPlanService $service): void
    {
        $plan = $this->assertOwnsPlan($planId);

        try {
            $service->restorePlan(auth()->user(), $plan);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Tidak bisa memulihkan rencana.';
            session()->flash('error', $message);

            return;
        }

        $this->sidebarTab = 'active';
        $this->selectedPlanId = $plan->id;
        session()->flash('success', 'Rencana dipulihkan dan kembali ke daftar aktif (memakai 1 slot rencana).');
    }

    public function deletePlan(int $planId, LearningPlanService $service): void
    {
        $plan = $this->assertOwnsPlan($planId);
        $service->deletePlan($plan);

        if ($this->selectedPlanId === $planId) {
            $this->selectedPlanId = $this->sidebarTab === 'archive'
                ? $service->archivedPlansFor(auth()->user())->first()?->id
                : $service->plansFor(auth()->user())->first()?->id;
        }

        session()->flash('success', 'Rencana dihapus.');
    }

    public function openCreateTaskModal(?string $status = 'todo', ?int $parentId = null): void
    {
        if (! $this->selectedPlanId) {
            session()->flash('error', 'Buat rencana belajar terlebih dahulu.');

            return;
        }

        $this->resetTaskForm();
        $this->editingTaskId = null;
        $this->parentTaskId = $parentId;
        $this->taskStatus = LearningPlanTaskStatus::tryFrom((string) $status)?->value ?? 'todo';
        $this->showTaskModal = true;
    }

    public function openEditTaskModal(int $taskId): void
    {
        $task = $this->assertOwnsTask($taskId);

        $this->editingTaskId = $task->id;
        $this->parentTaskId = $task->parent_id;
        $this->taskTitle = $task->title;
        $this->taskNotes = (string) ($task->notes ?? '');
        $this->taskCategory = $task->category->value;
        $this->taskPriority = $task->priority->value;
        $this->taskStatus = $task->status->value;
        $this->taskScheduledAt = $task->scheduled_at?->format('Y-m-d') ?? '';
        $this->showTaskModal = true;
    }

    public function saveTask(LearningPlanService $service): void
    {
        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:160'],
            'taskNotes' => ['nullable', 'string', 'max:2000'],
            'taskCategory' => ['required', Rule::enum(LearningPlanTaskCategory::class)],
            'taskPriority' => ['required', Rule::enum(LearningPlanPriority::class)],
            'taskStatus' => ['required', Rule::enum(LearningPlanTaskStatus::class)],
            'taskScheduledAt' => ['nullable', 'date'],
        ], [
            'taskTitle.required' => 'Judul tugas wajib diisi.',
            'taskTitle.max' => 'Judul tugas maksimal 160 karakter.',
            'taskNotes.max' => 'Catatan maksimal 2000 karakter.',
            'taskCategory.required' => 'Kategori wajib dipilih.',
            'taskCategory.enum' => 'Kategori tidak valid.',
            'taskPriority.required' => 'Prioritas wajib dipilih.',
            'taskPriority.enum' => 'Prioritas tidak valid.',
            'taskStatus.required' => 'Status wajib dipilih.',
            'taskStatus.enum' => 'Status tidak valid.',
            'taskScheduledAt.date' => 'Jadwal tidak valid.',
        ], [
            'taskTitle' => 'judul tugas',
            'taskNotes' => 'catatan',
            'taskCategory' => 'kategori',
            'taskPriority' => 'prioritas',
            'taskStatus' => 'status',
            'taskScheduledAt' => 'jadwal',
        ]);

        $payload = [
            'title' => $validated['taskTitle'],
            'notes' => $validated['taskNotes'] ?: null,
            'category' => $validated['taskCategory'],
            'priority' => $validated['taskPriority'],
            'status' => $validated['taskStatus'],
            'scheduled_at' => $validated['taskScheduledAt'] ?: null,
        ];

        if ($this->editingTaskId) {
            $task = $this->assertOwnsTask($this->editingTaskId);
            $service->updateTask($task, $payload);
            session()->flash('success', 'Tugas diperbarui.');
        } else {
            $plan = $this->assertOwnsPlan((int) $this->selectedPlanId);
            $payload['parent_id'] = $this->parentTaskId;
            $service->createTask($plan, $payload);
            session()->flash('success', $this->parentTaskId ? 'Sub-tugas ditambahkan.' : 'Tugas ditambahkan.');
        }

        $this->showTaskModal = false;
        $this->resetTaskForm();
    }

    public function deleteTask(int $taskId, LearningPlanService $service): void
    {
        $task = $this->assertOwnsTask($taskId);
        $service->deleteTask($task);
        session()->flash('success', 'Tugas dihapus.');
    }

    public function toggleSubtask(int $taskId, LearningPlanService $service): void
    {
        $task = $this->assertOwnsTask($taskId);
        $plan = $task->plan;

        if ($plan->status === LearningPlanStatus::Archived) {
            session()->flash('error', 'Rencana arsip hanya bisa dilihat. Pulihkan terlebih dahulu untuk mengubah tugas.');

            return;
        }

        $service->toggleSubtask($task);
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function reorderBoard(string $status, array $orderedIds, LearningPlanService $service): void
    {
        $taskStatus = LearningPlanTaskStatus::tryFrom($status);

        if (! $taskStatus || ! $this->selectedPlanId) {
            return;
        }

        $plan = $this->assertOwnsPlan($this->selectedPlanId);
        $ids = array_values(array_filter(array_map('intval', $orderedIds)));

        $service->reorderBoard($plan, $taskStatus, $ids);

        // DOM sudah diupdate SortableJS — skip re-render agar instance drag tidak rusak.
        $this->skipRender();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->subMonth();
        $this->calendarYear = (int) $date->year;
        $this->calendarMonth = (int) $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->addMonth();
        $this->calendarYear = (int) $date->year;
        $this->calendarMonth = (int) $date->month;
    }

    public function goToToday(): void
    {
        $this->calendarYear = (int) now()->year;
        $this->calendarMonth = (int) now()->month;
    }

    public function render(LearningPlanService $service)
    {
        $user = auth()->user();
        $plans = $service->plansFor($user);
        $archivedPlans = $service->archivedPlansFor($user);

        if ($this->sidebarTab === 'archive') {
            if ($this->selectedPlanId && ! $archivedPlans->contains('id', $this->selectedPlanId)) {
                $this->selectedPlanId = $archivedPlans->first()?->id;
            }
        } elseif ($this->selectedPlanId && ! $plans->contains('id', $this->selectedPlanId)) {
            $this->selectedPlanId = $plans->first()?->id;
        }

        $selectedPlan = $this->sidebarTab === 'archive'
            ? $archivedPlans->firstWhere('id', $this->selectedPlanId)
            : $plans->firstWhere('id', $this->selectedPlanId);

        $isArchivedView = $selectedPlan?->status === LearningPlanStatus::Archived;

        $board = [
            'todo' => collect(),
            'in_progress' => collect(),
            'done' => collect(),
        ];

        $tableTasks = collect();

        if ($selectedPlan) {
            $board = $service->boardColumns($selectedPlan);
            $tableTasks = $selectedPlan->rootTasks()->with('subtasks')->get();
        }

        $calendarTasks = $service->calendarTasks($user, $this->calendarYear, $this->calendarMonth);
        $calendarDays = $this->buildCalendarDays($this->calendarYear, $this->calendarMonth);
        $weaknessStats = app(ExamWeaknessAnalysisService::class)->getStatsForUser((int) $user->id);
        $aiGeneration = $service->aiGenerationAvailability($user, $weaknessStats);

        return view('livewire.peserta.rencana-belajar', [
            'plans' => $plans,
            'archivedPlans' => $archivedPlans,
            'archivedCount' => $archivedPlans->count(),
            'selectedPlan' => $selectedPlan,
            'isArchivedView' => $isArchivedView,
            'canRestorePlan' => $service->activeCount($user) < LearningPlan::MAX_ACTIVE_PLANS,
            'board' => $board,
            'tableTasks' => $tableTasks,
            'calendarTasks' => $calendarTasks,
            'calendarDays' => $calendarDays,
            'activeCount' => $service->activeCount($user),
            'completedToday' => $service->completedTasksToday($user),
            'categories' => LearningPlanTaskCategory::cases(),
            'priorities' => LearningPlanPriority::cases(),
            'taskStatuses' => LearningPlanTaskStatus::cases(),
            'planColors' => LearningPlan::COLORS,
            'maxPlans' => LearningPlan::MAX_ACTIVE_PLANS,
            'aiGeneration' => $aiGeneration,
        ]);
    }

    private function assertOwnsPlan(int $planId): LearningPlan
    {
        return LearningPlan::query()
            ->where('user_id', auth()->id())
            ->whereKey($planId)
            ->firstOrFail();
    }

    private function assertOwnsTask(int $taskId): LearningPlanTask
    {
        return LearningPlanTask::query()
            ->whereKey($taskId)
            ->whereHas('plan', fn ($q) => $q->where('user_id', auth()->id()))
            ->firstOrFail();
    }

    private function resetPlanForm(): void
    {
        $this->planTitle = '';
        $this->planDescription = '';
        $this->planPriority = 'medium';
        $this->planColor = 'indigo';
        $this->planStartsAt = '';
        $this->planEndsAt = '';
        $this->resetValidation();
    }

    private function resetTaskForm(): void
    {
        $this->taskTitle = '';
        $this->taskNotes = '';
        $this->taskCategory = 'materi';
        $this->taskPriority = 'medium';
        $this->taskStatus = 'todo';
        $this->taskScheduledAt = '';
        $this->parentTaskId = null;
        $this->resetValidation();
    }

    /**
     * @return list<array{date: ?Carbon, isCurrentMonth: bool, isToday: bool}>
     */
    private function buildCalendarDays(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days[] = [
                'date' => $cursor->copy(),
                'isCurrentMonth' => $cursor->month === $month,
                'isToday' => $cursor->isToday(),
            ];
            $cursor->addDay();
        }

        return $days;
    }
}
