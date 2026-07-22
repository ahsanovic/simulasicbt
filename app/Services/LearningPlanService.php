<?php

namespace App\Services;

use App\Enums\LearningPlanPriority;
use App\Enums\LearningPlanStatus;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\LearningPlanTaskStatus;
use App\Models\LearningPlan;
use App\Models\LearningPlanTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningPlanService
{
    /** @return Collection<int, LearningPlan> */
    public function plansFor(User $user): Collection
    {
        return LearningPlan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [LearningPlanStatus::Active, LearningPlanStatus::Completed])
            ->with([
                'rootTasks.subtasks',
            ])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, LearningPlan> */
    public function archivedPlansFor(User $user): Collection
    {
        return LearningPlan::query()
            ->where('user_id', $user->id)
            ->where('status', LearningPlanStatus::Archived)
            ->with(['rootTasks.subtasks'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    public function archivedCount(User $user): int
    {
        return LearningPlan::query()
            ->where('user_id', $user->id)
            ->where('status', LearningPlanStatus::Archived)
            ->count();
    }

    public function restorePlan(User $user, LearningPlan $plan): LearningPlan
    {
        if ($plan->status !== LearningPlanStatus::Archived) {
            throw ValidationException::withMessages([
                'plan' => 'Hanya rencana arsip yang bisa dipulihkan.',
            ]);
        }

        if ($this->activeCount($user) >= LearningPlan::MAX_ACTIVE_PLANS) {
            throw ValidationException::withMessages([
                'plan' => 'Maksimal '.LearningPlan::MAX_ACTIVE_PLANS.' rencana aktif. Arsipkan salah satu rencana terlebih dahulu sebelum memulihkan.',
            ]);
        }

        return $this->updatePlan($plan, ['status' => LearningPlanStatus::Active->value]);
    }

    public function activeCount(User $user): int
    {
        return LearningPlan::query()
            ->where('user_id', $user->id)
            ->where('status', LearningPlanStatus::Active)
            ->count();
    }

    public function completedTasksToday(User $user): int
    {
        return LearningPlanTask::query()
            ->whereHas('plan', fn ($q) => $q->where('user_id', $user->id))
            ->whereNull('parent_id')
            ->where('status', LearningPlanTaskStatus::Done)
            ->whereDate('completed_at', today())
            ->count();
    }

    /**
     * @param  array{title: string, description?: ?string, priority?: string, color?: string, starts_at?: ?string, ends_at?: ?string}  $data
     */
    public function createPlan(User $user, array $data): LearningPlan
    {
        if ($this->activeCount($user) >= LearningPlan::MAX_ACTIVE_PLANS) {
            throw ValidationException::withMessages([
                'title' => 'Maksimal '.LearningPlan::MAX_ACTIVE_PLANS.' rencana aktif. Arsipkan atau selesaikan salah satu terlebih dahulu.',
            ]);
        }

        $maxOrder = (int) LearningPlan::query()
            ->where('user_id', $user->id)
            ->max('sort_order');

        return LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => LearningPlanPriority::tryFrom($data['priority'] ?? '') ?? LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Active,
            'color' => $this->normalizeColor($data['color'] ?? 'indigo'),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);
    }

    /**
     * @param  array{title?: string, description?: ?string, priority?: string, color?: string, starts_at?: ?string, ends_at?: ?string, status?: string}  $data
     */
    public function updatePlan(LearningPlan $plan, array $data): LearningPlan
    {
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }
        if (isset($data['priority'])) {
            $payload['priority'] = LearningPlanPriority::tryFrom($data['priority']) ?? $plan->priority;
        }
        if (isset($data['color'])) {
            $payload['color'] = $this->normalizeColor($data['color']);
        }
        if (array_key_exists('starts_at', $data)) {
            $payload['starts_at'] = $data['starts_at'];
        }
        if (array_key_exists('ends_at', $data)) {
            $payload['ends_at'] = $data['ends_at'];
        }
        if (isset($data['status'])) {
            $payload['status'] = LearningPlanStatus::tryFrom($data['status']) ?? $plan->status;
        }

        $plan->update($payload);

        return $plan->refresh();
    }

    public function deletePlan(LearningPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * @param  array{title: string, category?: string, priority?: string, notes?: ?string, scheduled_at?: ?string, status?: string, parent_id?: ?int}  $data
     */
    public function createTask(LearningPlan $plan, array $data): LearningPlanTask
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = LearningPlanTask::query()
                ->where('learning_plan_id', $plan->id)
                ->whereKey($parentId)
                ->firstOrFail();

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'title' => 'Sub-tugas tidak bisa memiliki sub-tugas lagi.',
                ]);
            }
        }

        $maxOrder = (int) LearningPlanTask::query()
            ->where('learning_plan_id', $plan->id)
            ->where('parent_id', $parentId)
            ->when(
                $parentId === null,
                fn ($q) => $q->where('status', LearningPlanTaskStatus::tryFrom($data['status'] ?? '') ?? LearningPlanTaskStatus::Todo),
            )
            ->max('sort_order');

        $status = LearningPlanTaskStatus::tryFrom($data['status'] ?? '') ?? LearningPlanTaskStatus::Todo;

        return LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'parent_id' => $parentId,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'category' => LearningPlanTaskCategory::tryFrom($data['category'] ?? '') ?? LearningPlanTaskCategory::Lainnya,
            'priority' => LearningPlanPriority::tryFrom($data['priority'] ?? '') ?? LearningPlanPriority::Medium,
            'status' => $status,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'completed_at' => $status === LearningPlanTaskStatus::Done ? now() : null,
            'sort_order' => $maxOrder + 1,
        ]);
    }

    /**
     * @param  array{title?: string, category?: string, priority?: string, notes?: ?string, scheduled_at?: ?string, status?: string}  $data
     */
    public function updateTask(LearningPlanTask $task, array $data): LearningPlanTask
    {
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = $data['title'];
        }
        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $data['notes'];
        }
        if (isset($data['category'])) {
            $payload['category'] = LearningPlanTaskCategory::tryFrom($data['category']) ?? $task->category;
        }
        if (isset($data['priority'])) {
            $payload['priority'] = LearningPlanPriority::tryFrom($data['priority']) ?? $task->priority;
        }
        if (array_key_exists('scheduled_at', $data)) {
            $payload['scheduled_at'] = $data['scheduled_at'];
        }
        if (isset($data['status'])) {
            $status = LearningPlanTaskStatus::tryFrom($data['status']) ?? $task->status;
            $payload['status'] = $status;
            $payload['completed_at'] = $status === LearningPlanTaskStatus::Done
                ? ($task->completed_at ?? now())
                : null;
        }

        $task->update($payload);

        return $task->refresh();
    }

    public function deleteTask(LearningPlanTask $task): void
    {
        $task->delete();
    }

    public function toggleSubtask(LearningPlanTask $subtask): LearningPlanTask
    {
        if ($subtask->parent_id === null) {
            throw ValidationException::withMessages([
                'task' => 'Hanya sub-tugas yang bisa di-toggle dengan cara ini.',
            ]);
        }

        if ($subtask->status === LearningPlanTaskStatus::Done) {
            $subtask->markOpen(LearningPlanTaskStatus::Todo);
        } else {
            $subtask->markDone();
        }

        return $subtask->refresh();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderBoard(LearningPlan $plan, LearningPlanTaskStatus $status, array $orderedIds): void
    {
        DB::transaction(function () use ($plan, $status, $orderedIds) {
            foreach ($orderedIds as $index => $taskId) {
                $task = LearningPlanTask::query()
                    ->where('learning_plan_id', $plan->id)
                    ->whereNull('parent_id')
                    ->whereKey($taskId)
                    ->first();

                if (! $task) {
                    continue;
                }

                $wasDone = $task->status === LearningPlanTaskStatus::Done;
                $nowDone = $status === LearningPlanTaskStatus::Done;

                $task->forceFill([
                    'status' => $status,
                    'sort_order' => $index + 1,
                    'completed_at' => $nowDone
                        ? ($wasDone ? $task->completed_at : now())
                        : null,
                ])->save();
            }
        });
    }

    /**
     * @return array{todo: Collection<int, LearningPlanTask>, in_progress: Collection<int, LearningPlanTask>, done: Collection<int, LearningPlanTask>}
     */
    public function boardColumns(LearningPlan $plan): array
    {
        $roots = $plan->rootTasks()->with('subtasks')->get();

        return [
            'todo' => $roots->where('status', LearningPlanTaskStatus::Todo)->values(),
            'in_progress' => $roots->where('status', LearningPlanTaskStatus::InProgress)->values(),
            'done' => $roots->where('status', LearningPlanTaskStatus::Done)->values(),
        ];
    }

    /**
     * Tasks for a calendar month keyed by Y-m-d.
     *
     * @return Collection<string, Collection<int, LearningPlanTask>>
     */
    public function calendarTasks(User $user, int $year, int $month): Collection
    {
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return LearningPlanTask::query()
            ->whereNull('parent_id')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start->toDateString(), $end->toDateString()])
            ->whereHas('plan', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereIn('status', [LearningPlanStatus::Active, LearningPlanStatus::Completed]))
            ->with('plan')
            ->orderBy('scheduled_at')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (LearningPlanTask $task) => $task->scheduled_at->format('Y-m-d'));
    }

    /**
     * Tandai 1 tugas terbuka (root) yang cocok kategorinya sebagai selesai.
     * Prioritas: in_progress → terjadwal hari ini → todo tertua.
     */
    public function completeMatchingTasks(User $user, LearningPlanTaskCategory $category): int
    {
        $candidates = LearningPlanTask::query()
            ->whereNull('parent_id')
            ->where('category', $category)
            ->whereIn('status', [LearningPlanTaskStatus::Todo, LearningPlanTaskStatus::InProgress])
            ->whereHas('plan', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', LearningPlanStatus::Active))
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN scheduled_at = ? THEN 0 WHEN scheduled_at IS NULL THEN 1 WHEN scheduled_at < ? THEN 2 ELSE 3 END', [
                today()->toDateString(),
                today()->toDateString(),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(1)
            ->get();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $task = $candidates->first();
        $task->markDone();

        return 1;
    }

    /**
     * Snapshot evaluasi untuk mencegah generate rencana AI duplikat.
     *
     * @param  array<string, mixed>  $stats
     */
    public function evaluationSnapshotHash(array $stats): string
    {
        $weakMaterials = collect($stats['materials'] ?? [])
            ->filter(fn ($material) => is_array($material) && in_array($material['status'] ?? '', ['kritis', 'cukup'], true))
            ->sortBy([
                fn ($material) => ($material['status'] ?? '') === 'kritis' ? 0 : 1,
                fn ($material) => (float) ($material['percentage'] ?? 100),
            ])
            ->take(5)
            ->map(fn ($material) => [
                'material_id' => (int) ($material['material_id'] ?? 0),
                'percentage' => (int) round((float) ($material['percentage'] ?? 0)),
                'status' => (string) ($material['status'] ?? ''),
            ])
            ->values()
            ->all();

        $pillars = collect($stats['pillars'] ?? [])
            ->filter(fn ($pillar) => is_array($pillar))
            ->mapWithKeys(fn ($pillar, $code) => [
                (string) $code => (int) round((float) ($pillar['percentage'] ?? 0)),
            ])
            ->sortKeys()
            ->all();

        $payload = [
            'latest_attempt_at' => $stats['latest_attempt_at'] ?? null,
            'total_simulations' => (int) ($stats['total_simulations'] ?? 0),
            'pillars' => $pillars,
            'weak_materials' => $weakMaterials,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{
     *     status: 'no_simulation'|'max_plans'|'already_generated'|'available',
     *     message: string,
     *     existing_plan: ?LearningPlan,
     *     snapshot_hash: ?string,
     * }
     */
    public function aiGenerationAvailability(User $user, array $stats): array
    {
        if (($stats['total_simulations'] ?? 0) < 1) {
            return [
                'status' => 'no_simulation',
                'message' => 'Selesaikan simulasi terlebih dahulu agar rencana bisa digenerate dari evaluasi.',
                'existing_plan' => null,
                'snapshot_hash' => null,
            ];
        }

        if ($this->activeCount($user) >= LearningPlan::MAX_ACTIVE_PLANS) {
            return [
                'status' => 'max_plans',
                'message' => 'Maksimal '.LearningPlan::MAX_ACTIVE_PLANS.' rencana aktif. Arsipkan atau selesaikan salah satu terlebih dahulu.',
                'existing_plan' => null,
                'snapshot_hash' => null,
            ];
        }

        $snapshotHash = $this->evaluationSnapshotHash($stats);
        $existingPlan = $this->findActiveAiPlanForSnapshot($user, $snapshotHash);

        if ($existingPlan) {
            return [
                'status' => 'already_generated',
                'message' => 'Rencana untuk evaluasi ini sudah dibuat. Selesaikan simulasi baru untuk membuat rencana terbaru.',
                'existing_plan' => $existingPlan,
                'snapshot_hash' => $snapshotHash,
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Buat rencana 7 hari otomatis dari materi lemah hasil evaluasi terbaru.',
            'existing_plan' => null,
            'snapshot_hash' => $snapshotHash,
        ];
    }

    public function findActiveAiPlanForSnapshot(User $user, string $snapshotHash): ?LearningPlan
    {
        return LearningPlan::query()
            ->where('user_id', $user->id)
            ->where('source_evaluation_hash', $snapshotHash)
            ->where('status', LearningPlanStatus::Active)
            ->latest('id')
            ->first();
    }

    /**
     * Buat rencana belajar 7 hari dari weakness_stats Evaluasi AI.
     *
     * @param  array<string, mixed>  $stats
     */
    public function generateFromWeaknessStats(User $user, array $stats): LearningPlan
    {
        $availability = $this->aiGenerationAvailability($user, $stats);

        if ($availability['status'] === 'no_simulation') {
            throw ValidationException::withMessages([
                'plan' => $availability['message'],
            ]);
        }

        if ($availability['status'] === 'max_plans') {
            throw ValidationException::withMessages([
                'plan' => $availability['message'],
            ]);
        }

        if ($availability['status'] === 'already_generated' && $availability['existing_plan']) {
            throw ValidationException::withMessages([
                'plan' => $availability['message'],
            ]);
        }

        $snapshotHash = $availability['snapshot_hash'];

        $weakMaterials = collect($stats['materials'] ?? [])
            ->filter(fn ($m) => is_array($m) && in_array($m['status'] ?? '', ['kritis', 'cukup'], true))
            ->sortBy([
                fn ($m) => ($m['status'] ?? '') === 'kritis' ? 0 : 1,
                fn ($m) => (float) ($m['percentage'] ?? 100),
            ])
            ->values()
            ->take(5);

        $pillars = collect($stats['pillars'] ?? []);
        $weakestPillarKey = $pillars
            ->filter(fn ($p) => is_array($p))
            ->sortBy(fn ($p) => (float) ($p['percentage'] ?? 100))
            ->keys()
            ->first();

        $weakestPillarLabel = is_string($weakestPillarKey)
            ? (string) ($pillars[$weakestPillarKey]['label'] ?? strtoupper($weakestPillarKey))
            : 'SKD';

        $criticalCount = $weakMaterials->where('status', 'kritis')->count();
        $start = today();

        return DB::transaction(function () use ($user, $weakMaterials, $weakestPillarLabel, $criticalCount, $start, $stats, $snapshotHash) {
            $plan = $this->createPlan($user, [
                'title' => 'Fokus Perbaikan SKD — '.$start->translatedFormat('d M Y'),
                'description' => $this->buildPlanDescription($stats, $weakMaterials->count(), $weakestPillarLabel),
                'priority' => $criticalCount > 0
                    ? LearningPlanPriority::High->value
                    : LearningPlanPriority::Medium->value,
                'color' => $criticalCount > 0 ? 'rose' : 'indigo',
                'starts_at' => $start->toDateString(),
                'ends_at' => $start->copy()->addDays(6)->toDateString(),
            ]);

            $plan->forceFill(['source_evaluation_hash' => $snapshotHash])->save();

            $dayOffset = 0;

            foreach ($weakMaterials as $material) {
                $name = (string) ($material['display_name'] ?? $material['name'] ?? 'Materi');
                $pct = (int) round((float) ($material['percentage'] ?? 0));
                $statusLabel = (string) ($material['status_label'] ?? $material['status'] ?? '');
                $isCritical = ($material['status'] ?? '') === 'kritis';

                $materiTask = $this->createTask($plan, [
                    'title' => 'Pelajari: '.$name,
                    'category' => LearningPlanTaskCategory::Materi->value,
                    'priority' => $isCritical
                        ? LearningPlanPriority::High->value
                        : LearningPlanPriority::Medium->value,
                    'notes' => "Penguasaan saat ini {$pct}%. {$statusLabel}. Baca cheat-sheet lalu tandai selesai.",
                    'scheduled_at' => $start->copy()->addDays($dayOffset)->toDateString(),
                    'status' => LearningPlanTaskStatus::Todo->value,
                ]);

                $this->createTask($plan, [
                    'parent_id' => $materiTask->id,
                    'title' => 'Baca & pahami inti materi',
                    'category' => LearningPlanTaskCategory::Materi->value,
                    'priority' => LearningPlanPriority::Medium->value,
                ]);

                $this->createTask($plan, [
                    'parent_id' => $materiTask->id,
                    'title' => 'Catat poin yang masih bingung',
                    'category' => LearningPlanTaskCategory::Catatan->value,
                    'priority' => LearningPlanPriority::Low->value,
                ]);

                $dayOffset++;
            }

            $this->createTask($plan, [
                'title' => 'Review Kartu Sakti materi lemah',
                'category' => LearningPlanTaskCategory::KartuSakti->value,
                'priority' => LearningPlanPriority::High->value,
                'notes' => 'Jalankan sesi review harian agar materi masuk ingatan jangka panjang.',
                'scheduled_at' => $start->copy()->addDays(min($dayOffset, 3))->toDateString(),
            ]);

            $this->createTask($plan, [
                'title' => "Drill soal {$weakestPillarLabel} (fokus kelemahan)",
                'category' => LearningPlanTaskCategory::Drill->value,
                'priority' => LearningPlanPriority::High->value,
                'notes' => "Latihan terarah untuk pilar {$weakestPillarLabel} yang masih lemah.",
                'scheduled_at' => $start->copy()->addDays(min($dayOffset + 1, 4))->toDateString(),
            ]);

            $this->createTask($plan, [
                'title' => 'Latihan Audio Mode — review sambil dengar',
                'category' => LearningPlanTaskCategory::Audio->value,
                'priority' => LearningPlanPriority::Medium->value,
                'scheduled_at' => $start->copy()->addDays(min($dayOffset + 2, 5))->toDateString(),
            ]);

            $this->createTask($plan, [
                'title' => 'Simulasi SKD penuh — ukur progres',
                'category' => LearningPlanTaskCategory::TryOut->value,
                'priority' => LearningPlanPriority::Urgent->value,
                'notes' => 'Kerjakan simulasi penuh untuk mengukur apakah fokus perbaikan berhasil.',
                'scheduled_at' => $start->copy()->addDays(6)->toDateString(),
            ]);

            $this->createTask($plan, [
                'title' => 'Cek ulang Evaluasi Kesiapan AI',
                'category' => LearningPlanTaskCategory::Evaluasi->value,
                'priority' => LearningPlanPriority::Medium->value,
                'notes' => 'Setelah try out, perbarui rekomendasi AI dan bandingkan health bar materi.',
                'scheduled_at' => $start->copy()->addDays(6)->toDateString(),
            ]);

            return $plan->fresh(['rootTasks.subtasks']);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $weakMaterials
     */
    private function buildPlanDescription(array $stats, int $weakCount, string $weakestPillarLabel): string
    {
        $pillars = collect($stats['pillars'] ?? [])
            ->filter(fn ($p) => is_array($p))
            ->map(fn ($p, $code) => strtoupper((string) $code).' '.((int) round((float) ($p['percentage'] ?? 0))).'%')
            ->implode(' · ');

        $parts = [
            'Digenerate otomatis dari hasil evaluasi kesiapan.',
        ];

        if ($pillars !== '') {
            $parts[] = "Snapshot: {$pillars}.";
        }

        if ($weakCount > 0) {
            $parts[] = "Fokus {$weakCount} materi lemah + drill {$weakestPillarLabel}.";
        } else {
            $parts[] = "Pertahankan performa dengan drill {$weakestPillarLabel} dan try out.";
        }

        return implode(' ', $parts);
    }

    private function normalizeColor(string $color): string
    {
        return array_key_exists($color, LearningPlan::COLORS) ? $color : 'indigo';
    }
}
