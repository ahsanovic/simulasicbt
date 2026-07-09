<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Dashboard;
use App\Livewire\Peserta\LeaderboardHub;
use App\Models\AudioLearningSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_leaderboard_hub_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.leaderboard.index'))
            ->assertOk()
            ->assertSee('Papan Peringkat')
            ->assertSee('Skor Terbaik')
            ->assertSee('Hall of Fame XP');
    }

    public function test_leaderboard_hub_can_switch_to_xp_tab(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        AudioLearningSession::query()->create([
            'user_id' => $user->id,
            'subject_code' => 'twk',
            'question_count' => 10,
            'xp_earned' => 150,
            'duration_seconds' => 600,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(LeaderboardHub::class)
            ->call('setTab', 'xp')
            ->assertSet('tab', 'xp')
            ->assertSee('Hall of Fame — Total XP')
            ->assertSee('Cara dapat XP')
            ->assertSee('Kartu Sakti')
            ->assertSee('150 XP');
    }

    public function test_leaderboard_hub_score_tab_shows_simulation_cta(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(LeaderboardHub::class)
            ->assertSet('tab', 'score')
            ->assertSee('Mulai Simulasi')
            ->assertSee('Tantang skor terbaikmu — mulai simulasi sekarang.')
            ->assertSeeHtml(route('peserta.dashboard'));
    }

    public function test_leaderboard_hub_duel_tab_shows_duel_cta(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(LeaderboardHub::class)
            ->call('setTab', 'duel')
            ->assertSet('tab', 'duel')
            ->assertSee('Main Duel')
            ->assertSee('Naik peringkat duel — tantang lawan sekarang.')
            ->assertSeeHtml(route('peserta.duel.index'));
    }

    public function test_dashboard_shows_leaderboard_summary_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd',
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
            'total_score' => 420,
        ]);

        XpReward::query()->create([
            'user_id' => $user->id,
            'source_type' => 'test',
            'source_id' => 1,
            'amount' => GamificationService::TESTIMONIAL_XP_REWARD,
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Papan Peringkat')
            ->assertSee('#1')
            ->assertSee('Lihat Semua');
    }
}
