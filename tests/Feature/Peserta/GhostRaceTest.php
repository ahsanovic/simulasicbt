<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Dashboard;
use App\Livewire\Peserta\GhostRaceTrack;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\User;
use App\Services\LeaderboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GhostRaceTest extends TestCase
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

    public function test_dashboard_hides_ghost_race_without_exam_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertDontSee('Ghost Race');
    }

    public function test_dashboard_shows_ghost_race_after_first_simulation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->seedSubmittedAttempt($user, 360, 75, 90, 175);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Ghost Race')
            ->assertSee('Standar Kelulusan CPNS')
            ->assertSee('Pilih Target Jabatan');
    }

    public function test_dashboard_shows_formation_ghost_race_when_configured(): void
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

        $this->seedSubmittedAttempt($user, 340, 70, 85, 170);

        $leader = User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
        ]);
        $this->seedSubmittedAttempt($leader, 395, 90, 100, 180);

        for ($i = 0; $i < 8; $i++) {
            $other = User::factory()->create([
                'role' => UserRole::Peserta,
                'formation_id' => $formation->id,
            ]);
            $this->seedSubmittedAttempt($other, 320, 70, 85, 165);
        }

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Ghost Race')
            ->assertSee('Pranata Komputer')
            ->assertSee('Pelamar #')
            ->assertSee('Kejar Rival');
    }

    public function test_ghost_race_track_shows_weekly_recap_and_rival_picker(): void
    {
        $formation = Formation::query()->create([
            'name' => 'Pranata Komputer',
            'slug' => 'pranata-komputer-dua',
            'group' => 'Teknologi Informasi',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
            'formation_selected_at' => now(),
        ]);

        $this->seedSubmittedAttempt($user, 340, 70, 85, 170);

        $leader = User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
        ]);
        $this->seedSubmittedAttempt($leader, 395, 90, 100, 180);

        for ($i = 0; $i < 8; $i++) {
            $other = User::factory()->create([
                'role' => UserRole::Peserta,
                'formation_id' => $formation->id,
            ]);
            $this->seedSubmittedAttempt($other, 320, 70, 85, 165);
        }

        Livewire::actingAs($user)
            ->test(GhostRaceTrack::class)
            ->assertSee('Rekap Minggu Ini')
            ->assertSee('Pilih Rival')
            ->call('toggleNotifications')
            ->assertSee('Notif mati');
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
