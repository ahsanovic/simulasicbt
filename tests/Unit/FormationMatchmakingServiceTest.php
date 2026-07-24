<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\User;
use App\Services\FormationMatchmakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FormationMatchmakingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_analyze_returns_safe_zone_for_top_five_percent(): void
    {
        $formation = $this->createFormation('Pranata Komputer');
        $leader = $this->createApplicant($formation, 395, 90, 100, 180);
        $this->createApplicantPool($formation, 19, 320);

        $analysis = app(FormationMatchmakingService::class)->analyzeForUser($leader->fresh('formation'));

        $this->assertTrue($analysis['has_history']);
        $this->assertSame('safe', $analysis['zone']);
        $this->assertSame(1, $analysis['rank']);
        $this->assertStringContainsString('Top 5%', $analysis['message']);
    }

    public function test_analyze_returns_caution_zone_when_passing_but_not_top_rank(): void
    {
        $formation = $this->createFormation('Auditor');
        $this->createApplicant($formation, 400, 90, 100, 190);
        $this->createApplicant($formation, 390, 88, 98, 184);
        $this->createApplicant($formation, 380, 85, 95, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicant($formation, 320, 68, 82, 165);

        $analysis = app(FormationMatchmakingService::class)->analyzeForUser($user->fresh('formation'));

        $this->assertSame('caution', $analysis['zone']);
        $this->assertSame(5, $analysis['applicant_count']);
        $this->assertSame(4, $analysis['rank']);
        $this->assertNotNull($analysis['improvement']);
    }

    public function test_analyze_hides_rank_when_applicant_count_is_below_threshold(): void
    {
        $formation = $this->createFormation('Analis Hukum');
        $user = $this->createApplicant($formation, 360, 75, 90, 175);
        $this->createApplicant($formation, 330, 70, 85, 170);

        Cache::flush();

        $analysis = app(FormationMatchmakingService::class)->analyzeForUser($user->fresh('formation'));

        $this->assertTrue($analysis['insufficient_data']);
        $this->assertNull($analysis['rank']);
    }

    public function test_past_attempts_count_after_formation_is_selected(): void
    {
        $formation = $this->createFormation('Dokter');
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->createAttemptForUser($user, 380, 80, 95, 170);

        app(FormationMatchmakingService::class)->assignFormation($user, $formation->id);

        $analysis = app(FormationMatchmakingService::class)->analyzeForUser($user->fresh('formation'));

        $this->assertSame(380, $analysis['user_scores']['total']);
    }

    private function createFormation(string $name): Formation
    {
        return Formation::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'group' => 'Teknologi Informasi',
        ]);
    }

    private function createApplicant(Formation $formation, int $total, int $twk, int $tiu, int $tkp): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
            'formation_selected_at' => now(),
        ]);

        $this->createAttemptForUser($user, $total, $twk, $tiu, $tkp);

        return $user;
    }

    private function createApplicantPool(Formation $formation, int $count, int $total): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createApplicant($formation, $total, 65, 80, max(166, $total - 145));
        }
    }

    private function createAttemptForUser(User $user, int $total, int $twk, int $tiu, int $tkp): ExamAttempt
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
