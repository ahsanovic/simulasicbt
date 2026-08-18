<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\ScoreTrendPeriod;
use App\Enums\SkdTarget;
use App\Enums\UserRole;
use App\Livewire\Peserta\Statistik;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\LeaderboardSummaryService;
use App\Services\PesertaStatisticsService;
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
            ->assertSee('7 Hari')
            ->assertSee('30 Hari')
            ->assertSee('data-score-trend-chart', false)
            ->assertSee('data-score-trend-payload', false)
            ->assertSee('Skor Terbaik per Pilar')
            ->assertSee('Rekomendasi AI')
            ->assertSee('Minta Rekomendasi AI')
            ->assertSee('Aktivitas')
            ->assertSee('Gamifikasi')
            ->assertDontSee('Belum Ada Data Statistik');
    }

    public function test_score_trend_period_filter_limits_chart_data(): void
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
            'started_at' => now()->subDays(40),
            'submitted_at' => now()->subDays(40),
            'expires_at' => now()->subDays(39),
            'score_twk' => 100,
            'score_tiu' => 100,
            'score_tkp' => 140,
            'total_score' => 340,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'expires_at' => now(),
            'score_twk' => 130,
            'score_tiu' => 120,
            'score_tkp' => 160,
            'total_score' => 410,
        ]);

        $service = app(PesertaStatisticsService::class);

        $allTrend = $service->forUser($user, ScoreTrendPeriod::All)['score_trend'];
        $monthTrend = $service->forUser($user, ScoreTrendPeriod::Days30)['score_trend'];

        $this->assertCount(2, $allTrend['labels']);
        $this->assertCount(1, $monthTrend['labels']);
        $this->assertSame([410], $monthTrend['totals']);

        Livewire::actingAs($user)
            ->test(Statistik::class)
            ->call('setScoreTrendPeriod', ScoreTrendPeriod::Days30->value)
            ->assertSee('dalam 30 hari');
    }

    public function test_skd_target_filter_limits_statistics_to_selected_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $cpnsExam = Exam::query()->create([
            'title' => 'Simulasi CPNS',
            'slug' => 'simulasi-cpns-filter-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::Cpns->value,
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        $kedinasanExam = Exam::query()->create([
            'title' => 'Simulasi Kedinasan',
            'slug' => 'simulasi-kedinasan-filter-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::SekolahKedinasan->value,
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $cpnsExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::Cpns,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'expires_at' => now()->subDays(1),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $kedinasanExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::SekolahKedinasan,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'expires_at' => now(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 160,
            'total_score' => 315,
        ]);

        $service = app(PesertaStatisticsService::class);

        $allStats = $service->forUser($user);
        $cpnsStats = $service->forUser($user, skdTargetFilter: SkdTarget::Cpns);
        $kedinasanStats = $service->forUser($user, skdTargetFilter: SkdTarget::SekolahKedinasan);

        $this->assertSame(2, $allStats['overview']['total_simulations']);
        $this->assertSame(1, $cpnsStats['overview']['total_simulations']);
        $this->assertSame(1, $kedinasanStats['overview']['total_simulations']);
        $this->assertTrue($allStats['show_all_passing_profiles']);
        $this->assertSame(166, $cpnsStats['passing_grades']['tkp']);
        $this->assertSame(156, $kedinasanStats['passing_grades']['tkp']);

        Livewire::actingAs($user)
            ->test(Statistik::class)
            ->assertSee('Jenis Simulasi SKD')
            ->assertSee('CPNS')
            ->assertSee('Sekolah Kedinasan')
            ->call('setSkdTargetFilter', SkdTarget::SekolahKedinasan->value)
            ->assertSee('Standar Sekolah Kedinasan')
            ->assertSee('Ringkasan Performa')
            ->call('setSkdTargetFilter', 'all')
            ->assertSee('Ambang batas mengikuti jenis simulasi');
    }

    public function test_skd_target_filter_shows_empty_state_when_no_matching_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $cpnsExam = Exam::query()->create([
            'title' => 'Simulasi CPNS',
            'slug' => 'simulasi-cpns-empty-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::Cpns->value,
            ],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $cpnsExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::Cpns,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'expires_at' => now(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        Livewire::actingAs($user)
            ->test(Statistik::class)
            ->call('setSkdTargetFilter', SkdTarget::SekolahKedinasan->value)
            ->assertSee('Belum Ada Simulasi untuk Filter Ini')
            ->assertDontSee('Ringkasan Performa');
    }
}
