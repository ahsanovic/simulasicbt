<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Statistik;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\LeaderboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatistikTest extends TestCase
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

    public function test_guest_cannot_access_statistik_page(): void
    {
        $this->get(route('peserta.statistik.index'))
            ->assertRedirect(route('login'));
    }

    public function test_peserta_sees_empty_state_without_exam_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Statistik::class)
            ->assertSee('Statistik Saya')
            ->assertSee('Belum Ada Data Statistik')
            ->assertSee('Mulai Simulasi Pertama');
    }

    public function test_peserta_sees_statistics_after_submitting_simulation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi CPNS',
            'slug' => 'simulasi-cpns-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'score_twk' => 120,
            'score_tiu' => 110,
            'score_tkp' => 150,
            'total_score' => 380,
        ]);

        Livewire::actingAs($user)
            ->test(Statistik::class)
            ->assertSee('Ringkasan Performa')
            ->assertSee('Progres Skor Simulasi')
            ->assertSee('data-score-trend-chart', false)
            ->assertSee('data-score-trend-payload', false)
            ->assertSee('Skor Terbaik per Pilar')
            ->assertSee('Rekomendasi AI')
            ->assertSee('Minta Rekomendasi AI')
            ->assertSee('Aktivitas')
            ->assertSee('Gamifikasi')
            ->assertDontSee('Belum Ada Data Statistik');
    }
}
