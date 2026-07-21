<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Dashboard;
use App\Livewire\Peserta\ExamHistory;
use App\Livewire\Peserta\SimulasiFormasi;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\User;
use App\Services\LeaderboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SimulasiFormasiTest extends TestCase
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

    public function test_page_is_accessible_for_peserta(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.simulasi-formasi'))
            ->assertOk()
            ->assertSee('Simulasi Kelulusan Formasi');
    }

    public function test_autocomplete_shows_not_found_message(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(SimulasiFormasi::class)
            ->set('formationSearch', 'Jabatan Tidak Ada')
            ->assertSee('Jabatan tidak ditemukan / belum tersedia');
    }

    public function test_selecting_formation_persists_choice(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $formation = Formation::query()->create([
            'name' => 'Pranata Komputer',
            'slug' => 'pranata-komputer',
            'group' => 'Teknologi Informasi',
        ]);

        Livewire::actingAs($user)
            ->test(SimulasiFormasi::class)
            ->call('selectFormation', $formation->id)
            ->assertSet('formationSearch', 'Pranata Komputer');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'formation_id' => $formation->id,
        ]);
    }

    public function test_changing_formation_requires_confirmation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $first = Formation::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'group' => 'Keuangan & Pengawasan',
        ]);
        $second = Formation::query()->create([
            'name' => 'Analis Hukum',
            'slug' => 'analis-hukum',
            'group' => 'Hukum & Tata Kelola',
        ]);

        $user->forceFill([
            'formation_id' => $first->id,
            'formation_selected_at' => now(),
        ])->save();

        Livewire::actingAs($user)
            ->test(SimulasiFormasi::class)
            ->call('selectFormation', $second->id)
            ->assertSet('showChangeConfirmation', true)
            ->assertSee('Ganti target jabatan?')
            ->call('confirmFormationChange')
            ->assertSet('showChangeConfirmation', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'formation_id' => $second->id,
        ]);
    }

    public function test_dashboard_shows_formation_cta_when_no_target_selected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->seedSubmittedAttempt($user, 360, 75, 90, 175);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Simulasi Formasi')
            ->assertSee('Pilih target jabatan');
    }

    public function test_dashboard_shows_formation_summary_when_configured(): void
    {
        $formation = Formation::query()->create([
            'name' => 'Pranata Komputer',
            'slug' => 'pranata-komputer',
            'group' => 'Teknologi Informasi',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
            'formation_selected_at' => now(),
        ]);

        $this->seedSubmittedAttempt($user, 395, 90, 100, 180);

        for ($i = 0; $i < 9; $i++) {
            $other = User::factory()->create([
                'role' => UserRole::Peserta,
                'formation_id' => $formation->id,
            ]);
            $this->seedSubmittedAttempt($other, 320, 70, 85, 165);
        }

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Simulasi Formasi')
            ->assertSee('Pranata Komputer');
    }

    public function test_exam_result_modal_shows_formation_cta(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->seedSubmittedAttempt($user, 360, 75, 90, 175);

        session(['show_result_attempt_id' => $attempt->id]);

        Livewire::actingAs($user)
            ->test(ExamHistory::class)
            ->assertSee('Simulasi Kelulusan Formasi')
            ->assertSee('Pilih target jabatan');
    }

    private function seedSubmittedAttempt(User $user, int $total, int $twk, int $tiu, int $tkp): ExamAttempt
    {
        $exam = Exam::query()->create([
            'title' => 'Simulasi '.uniqid(),
            'slug' => 'simulasi-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => User::factory()->create(['role' => UserRole::Admin])->id,
        ]);

        return ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => $twk,
            'score_tiu' => $tiu,
            'score_tkp' => $tkp,
            'total_score' => $total,
        ]);
    }
}
