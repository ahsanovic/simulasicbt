<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\LearningPlanPriority;
use App\Enums\LearningPlanStatus;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\LearningPlanTaskStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Dashboard;
use App\Livewire\Peserta\RencanaBelajar;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningPlan;
use App\Models\LearningPlanTask;
use App\Models\User;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\FlashcardService;
use App\Services\LeaderboardSummaryService;
use App\Services\LearningPlanService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RencanaBelajarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LeaderboardSummaryService::class, function ($mock): void {
            $mock->shouldReceive('getRanks')->andReturn([
                'score' => null,
                'duel' => null,
                'xp' => null,
            ]);
        });
    }

    public function test_dashboard_carousel_shows_rencana_belajar_feature(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Rencana Belajar')
            ->assertSee('Buka planner');
    }

    public function test_dashboard_carousel_shows_planner_progress_when_active(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana CPNS',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Active,
            'color' => 'indigo',
            'sort_order' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('1 rencana aktif');
    }

    public function test_peserta_can_view_rencana_belajar_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.rencana-belajar.index'))
            ->assertOk()
            ->assertSee('Rencana Belajar');
    }

    public function test_can_create_plan_and_task(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('openCreatePlanModal')
            ->set('planTitle', 'Persiapan SKD 30 Hari')
            ->set('planPriority', LearningPlanPriority::High->value)
            ->set('planColor', 'indigo')
            ->call('savePlan')
            ->assertHasNoErrors()
            ->assertSet('showPlanModal', false);

        $plan = LearningPlan::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame('Persiapan SKD 30 Hari', $plan->title);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->set('selectedPlanId', $plan->id)
            ->call('openCreateTaskModal', 'todo')
            ->set('taskTitle', 'Drill 20 soal TIU')
            ->set('taskCategory', LearningPlanTaskCategory::Drill->value)
            ->set('taskPriority', LearningPlanPriority::Medium->value)
            ->set('taskStatus', LearningPlanTaskStatus::Todo->value)
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('learning_plan_tasks', [
            'learning_plan_id' => $plan->id,
            'title' => 'Drill 20 soal TIU',
            'category' => LearningPlanTaskCategory::Drill->value,
            'status' => LearningPlanTaskStatus::Todo->value,
        ]);
    }

    public function test_cannot_create_more_than_max_active_plans(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(LearningPlanService::class);

        for ($i = 1; $i <= LearningPlan::MAX_ACTIVE_PLANS; $i++) {
            $service->createPlan($user, ['title' => "Rencana {$i}"]);
        }

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('openCreatePlanModal')
            ->set('planTitle', 'Rencana kelebihan')
            ->call('savePlan')
            ->assertHasErrors(['title']);
    }

    public function test_reorder_board_moves_task_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $plan = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Board',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Active,
            'color' => 'sky',
            'sort_order' => 1,
        ]);

        $taskA = LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'title' => 'Tugas A',
            'category' => LearningPlanTaskCategory::Materi,
            'priority' => LearningPlanPriority::Low,
            'status' => LearningPlanTaskStatus::Todo,
            'sort_order' => 1,
        ]);

        $taskB = LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'title' => 'Tugas B',
            'category' => LearningPlanTaskCategory::TryOut,
            'priority' => LearningPlanPriority::High,
            'status' => LearningPlanTaskStatus::Todo,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->set('selectedPlanId', $plan->id)
            ->call('reorderBoard', LearningPlanTaskStatus::InProgress->value, [$taskA->id])
            ->call('reorderBoard', LearningPlanTaskStatus::Todo->value, [$taskB->id]);

        $this->assertSame(LearningPlanTaskStatus::InProgress, $taskA->fresh()->status);
        $this->assertNotNull($taskA->fresh()->completed_at === null ? true : null);
        $this->assertNull($taskA->fresh()->completed_at);
        $this->assertSame(LearningPlanTaskStatus::Todo, $taskB->fresh()->status);
    }

    public function test_subtask_toggle_and_progress(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $plan = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Sub',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Active,
            'color' => 'emerald',
            'sort_order' => 1,
        ]);

        $parent = LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'title' => 'Baca materi TWK',
            'category' => LearningPlanTaskCategory::Materi,
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanTaskStatus::InProgress,
            'sort_order' => 1,
        ]);

        $sub = LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'parent_id' => $parent->id,
            'title' => 'Bab Pancasila',
            'category' => LearningPlanTaskCategory::Materi,
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanTaskStatus::Todo,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->set('selectedPlanId', $plan->id)
            ->call('toggleSubtask', $sub->id);

        $this->assertSame(LearningPlanTaskStatus::Done, $sub->fresh()->status);
        $this->assertSame(100, $parent->fresh()->load('subtasks')->subtaskProgress()['percent']);
    }

    public function test_cannot_access_other_user_plan(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Peserta]);
        $intruder = User::factory()->create(['role' => UserRole::Peserta]);

        $plan = LearningPlan::query()->create([
            'user_id' => $owner->id,
            'title' => 'Rahasia',
            'priority' => LearningPlanPriority::Urgent,
            'status' => LearningPlanStatus::Active,
            'color' => 'rose',
            'sort_order' => 1,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($intruder)
            ->test(RencanaBelajar::class)
            ->call('openEditPlanModal', $plan->id);
    }

    public function test_complete_matching_tasks_marks_best_candidate_done(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(LearningPlanService::class);

        $plan = $service->createPlan($user, ['title' => 'Auto Complete Plan']);

        $older = $service->createTask($plan, [
            'title' => 'Drill lama',
            'category' => LearningPlanTaskCategory::Drill->value,
            'status' => LearningPlanTaskStatus::Todo->value,
        ]);

        $inProgress = $service->createTask($plan, [
            'title' => 'Drill sedang',
            'category' => LearningPlanTaskCategory::Drill->value,
            'status' => LearningPlanTaskStatus::InProgress->value,
        ]);

        $completed = $service->completeMatchingTasks($user, LearningPlanTaskCategory::Drill);

        $this->assertSame(1, $completed);
        $this->assertSame(LearningPlanTaskStatus::Done, $inProgress->fresh()->status);
        $this->assertSame(LearningPlanTaskStatus::Todo, $older->fresh()->status);
    }

    public function test_flashcard_session_auto_completes_kartu_sakti_task(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(LearningPlanService::class);
        $plan = $service->createPlan($user, ['title' => 'Kartu Plan']);
        $task = $service->createTask($plan, [
            'title' => 'Review Kartu Sakti',
            'category' => LearningPlanTaskCategory::KartuSakti->value,
        ]);

        app(FlashcardService::class)->recordSession($user, 5, 60);

        $this->assertSame(LearningPlanTaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_generate_from_weakness_stats_creates_scheduled_tasks(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $service = app(LearningPlanService::class);

        $stats = [
            'total_simulations' => 2,
            'pillars' => [
                'twk' => ['label' => 'TWK', 'percentage' => 40, 'status' => 'kritis', 'status_label' => 'Butuh Perhatian'],
                'tiu' => ['label' => 'TIU', 'percentage' => 80, 'status' => 'aman', 'status_label' => 'Siap'],
                'tkp' => ['label' => 'TKP', 'percentage' => 70, 'status' => 'cukup', 'status_label' => 'Cukup'],
            ],
            'materials' => [
                [
                    'material_id' => 1,
                    'subject_code' => 'twk',
                    'name' => 'Nasionalisme',
                    'display_name' => 'TWK - Nasionalisme',
                    'percentage' => 35,
                    'status' => 'kritis',
                    'status_label' => 'Butuh Perhatian Khusus',
                ],
                [
                    'material_id' => 2,
                    'subject_code' => 'tkp',
                    'name' => 'Integritas',
                    'display_name' => 'TKP - Integritas',
                    'percentage' => 65,
                    'status' => 'cukup',
                    'status_label' => 'Cukup',
                ],
            ],
            'latest_attempt_at' => now()->toDateTimeString(),
            'time_management' => ['has_data' => false],
        ];

        $plan = $service->generateFromWeaknessStats($user, $stats);

        $this->assertSame(LearningPlanStatus::Active, $plan->status);
        $this->assertStringContainsString('Fokus Perbaikan SKD', $plan->title);

        $roots = $plan->rootTasks;
        $this->assertTrue($roots->contains(fn ($t) => $t->category === LearningPlanTaskCategory::Materi));
        $this->assertTrue($roots->contains(fn ($t) => $t->category === LearningPlanTaskCategory::Drill));
        $this->assertTrue($roots->contains(fn ($t) => $t->category === LearningPlanTaskCategory::TryOut));
        $this->assertTrue($roots->contains(fn ($t) => $t->category === LearningPlanTaskCategory::KartuSakti));
        $this->assertTrue($roots->contains(fn ($t) => $t->category === LearningPlanTaskCategory::Evaluasi));
        $this->assertGreaterThanOrEqual(2, $roots->where('category', LearningPlanTaskCategory::Materi)->count());
    }

    public function test_livewire_can_generate_from_evaluation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 40,
            'score_tiu' => 50,
            'score_tkp' => 60,
            'total_score' => 150,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('generateFromEvaluation')
            ->assertHasNoErrors()
            ->assertNotSet('selectedPlanId', null);

        $this->assertDatabaseHas('learning_plans', [
            'user_id' => $user->id,
        ]);
        $this->assertTrue(
            LearningPlanTask::query()
                ->whereHas('plan', fn ($q) => $q->where('user_id', $user->id))
                ->where('category', LearningPlanTaskCategory::TryOut)
                ->exists(),
        );
    }

    public function test_cannot_generate_duplicate_ai_plan_for_same_evaluation_snapshot(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 40,
            'score_tiu' => 50,
            'score_tkp' => 60,
            'total_score' => 150,
        ]);

        $service = app(LearningPlanService::class);
        $stats = app(ExamWeaknessAnalysisService::class)->getStatsForUser($user->id);
        $firstPlan = $service->generateFromWeaknessStats($user, $stats);

        $this->assertNotNull($firstPlan->source_evaluation_hash);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('generateFromEvaluation')
            ->assertSet('selectedPlanId', $firstPlan->id);

        $this->assertSame(1, LearningPlan::query()->where('user_id', $user->id)->count());
    }

    public function test_can_generate_new_ai_plan_after_new_simulation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 40,
            'score_tiu' => 50,
            'score_tkp' => 60,
            'total_score' => 150,
        ]);

        $service = app(LearningPlanService::class);
        $weaknessAnalysis = app(ExamWeaknessAnalysisService::class);
        $stats = $weaknessAnalysis->getStatsForUser($user->id);
        $service->generateFromWeaknessStats($user, $stats);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 55,
            'score_tiu' => 60,
            'score_tkp' => 65,
            'total_score' => 180,
        ]);

        $weaknessAnalysis->forget($user->id);
        $updatedStats = $weaknessAnalysis->getStatsForUser($user->id);
        $availability = $service->aiGenerationAvailability($user, $updatedStats);

        $this->assertSame('available', $availability['status']);

        $secondPlan = $service->generateFromWeaknessStats($user, $updatedStats);

        $this->assertNotSame(
            LearningPlan::query()->where('user_id', $user->id)->oldest('id')->value('source_evaluation_hash'),
            $secondPlan->source_evaluation_hash,
        );
        $this->assertSame(2, LearningPlan::query()->where('user_id', $user->id)->count());
    }

    public function test_archived_plan_appears_in_archive_tab(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $plan = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Arsip',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Archived,
            'color' => 'indigo',
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('setSidebarTab', 'archive')
            ->assertSet('sidebarTab', 'archive')
            ->assertSet('selectedPlanId', $plan->id)
            ->assertSee('Rencana Arsip')
            ->assertSee('Pulihkan');
    }

    public function test_restore_plan_uses_active_slot(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $archived = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Arsip',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Archived,
            'color' => 'indigo',
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('restorePlan', $archived->id)
            ->assertSet('sidebarTab', 'active')
            ->assertSet('selectedPlanId', $archived->id);

        $this->assertSame(LearningPlanStatus::Active, $archived->fresh()->status);
        $this->assertSame(1, app(LearningPlanService::class)->activeCount($user));
    }

    public function test_cannot_restore_when_active_slots_full(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        for ($i = 1; $i <= LearningPlan::MAX_ACTIVE_PLANS; $i++) {
            LearningPlan::query()->create([
                'user_id' => $user->id,
                'title' => "Rencana {$i}",
                'priority' => LearningPlanPriority::Medium,
                'status' => LearningPlanStatus::Active,
                'color' => 'indigo',
                'sort_order' => $i,
            ]);
        }

        $archived = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Arsip',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Archived,
            'color' => 'rose',
            'sort_order' => 99,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('setSidebarTab', 'archive')
            ->call('restorePlan', $archived->id)
            ->assertSet('sidebarTab', 'archive');

        $this->assertSame(LearningPlanStatus::Archived, $archived->fresh()->status);
        $this->assertSame(LearningPlan::MAX_ACTIVE_PLANS, app(LearningPlanService::class)->activeCount($user));
    }

    public function test_cannot_toggle_subtask_on_archived_plan(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $plan = LearningPlan::query()->create([
            'user_id' => $user->id,
            'title' => 'Rencana Arsip',
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanStatus::Archived,
            'color' => 'indigo',
            'sort_order' => 1,
        ]);

        $sub = LearningPlanTask::query()->create([
            'learning_plan_id' => $plan->id,
            'title' => 'Sub tugas',
            'category' => LearningPlanTaskCategory::Materi,
            'priority' => LearningPlanPriority::Medium,
            'status' => LearningPlanTaskStatus::Todo,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(RencanaBelajar::class)
            ->call('setSidebarTab', 'archive')
            ->set('selectedPlanId', $plan->id)
            ->call('toggleSubtask', $sub->id);

        $this->assertSame(LearningPlanTaskStatus::Todo, $sub->fresh()->status);
    }
}
