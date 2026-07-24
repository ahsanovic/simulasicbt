<?php

namespace Tests\Unit;

use App\DTOs\GhostRaceTrackState;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\GhostRaceTier;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\User;
use App\Models\XpReward;
use App\Notifications\GhostRivalPulledAhead;
use App\Services\FormationMatchmakingService;
use App\Services\GhostRaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GhostRaceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_track_state_is_hidden_without_exam_history(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $state = app(GhostRaceService::class)->getTrackState($user);

        $this->assertFalse($state->visible);
    }

    public function test_no_formation_tier_uses_passing_grade_ghost(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->createAttemptForUser($user, 340, 70, 85, 170);

        $state = app(GhostRaceService::class)->getTrackState($user->fresh());

        $this->assertTrue($state->visible);
        $this->assertSame(GhostRaceTier::NoFormation, $state->tier);
        $this->assertSame('Standar Kelulusan CPNS', $state->ghost->alias);
        $this->assertTrue($state->ghost->isSynthetic);
        $this->assertNotNull($state->cta);
        $this->assertSame(route('peserta.simulasi-formasi'), $state->cta['url']);
    }

    public function test_formation_sparse_tier_uses_aggregate_ghost(): void
    {
        $formation = $this->createFormation('Analis Hukum');
        $user = $this->createApplicant($formation, 360, 75, 90, 175);
        $this->createApplicant($formation, 330, 70, 85, 170);

        Cache::flush();

        $state = app(GhostRaceService::class)->getTrackState($user->fresh('formation'));

        $this->assertTrue($state->visible);
        $this->assertSame(GhostRaceTier::FormationSparse, $state->tier);
        $this->assertSame('Rata-rata Pelamar', $state->ghost->alias);
        $this->assertTrue($state->ghost->isSynthetic);
    }

    public function test_formation_full_tier_picks_top_rival_with_anonymous_alias(): void
    {
        $formation = $this->createFormation('Pranata Komputer');
        $leader = $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        Cache::flush();

        $state = app(GhostRaceService::class)->getTrackState($user->fresh('formation'));

        $this->assertTrue($state->visible);
        $this->assertSame(GhostRaceTier::FormationFull, $state->tier);
        $this->assertFalse($state->ghost->isSynthetic);
        $this->assertStringStartsWith('Pelamar #', $state->ghost->alias);
        $this->assertSame(
            'Pelamar #'.strtoupper(substr(md5((string) $leader->id), 0, 3)),
            $state->ghost->alias,
        );
        $this->assertGreaterThan($state->userPosition, $state->ghostPosition);
    }

    public function test_race_score_uses_weighted_components(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->createAttemptForUser($user, 550, 150, 175, 225);

        XpReward::query()->create([
            'user_id' => $user->id,
            'source_type' => ExamAttempt::class,
            'source_id' => 1,
            'amount' => 500,
        ]);

        $state = app(GhostRaceService::class)->getTrackState($user->fresh());

        $this->assertSame(100, $state->userScore->skdComponent);
        $this->assertSame(70, $state->userScore->activityComponent);
        $this->assertSame(
            (int) round(100 * 0.40 + 70 * 0.35 + $state->userScore->readinessComponent * 0.25),
            $state->userScore->total,
        );
    }

    public function test_user_leading_shows_positive_message(): void
    {
        $formation = $this->createFormation('Auditor');
        $user = $this->createApplicant($formation, 420, 95, 110, 190);
        $this->createApplicant($formation, 320, 70, 85, 165);
        $this->createApplicantPool($formation, 8, 300);

        Cache::flush();

        $state = app(GhostRaceService::class)->getTrackState($user->fresh('formation'));

        $this->assertSame(0, $state->gapPoints);
        $this->assertStringContainsString('memimpin', strtolower($state->message));
        $this->assertNull($state->cta);
    }

    public function test_track_state_round_trips_through_cache_array(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->createAttemptForUser($user, 360, 75, 90, 175);

        $service = app(GhostRaceService::class);
        $state = $service->getTrackState($user->fresh());

        $this->assertIsArray(Cache::get('ghost_race_track_v4_'.$user->id.'_none_auto'));
        $this->assertSame($state->toArray(), GhostRaceTrackState::fromArray($state->toArray())->toArray());
    }

    public function test_clearing_formation_refreshes_ghost_race_state(): void
    {
        $formation = $this->createFormation('Auditor');
        $user = $this->createApplicant($formation, 360, 75, 90, 175);

        Cache::flush();

        $service = app(GhostRaceService::class);
        $withFormation = $service->getTrackState($user->fresh('formation'));

        $this->assertSame('Auditor', $withFormation->formationName);
        $this->assertSame(GhostRaceTier::FormationSparse, $withFormation->tier);

        app(FormationMatchmakingService::class)->clearFormation($user->fresh());

        $withoutFormation = $service->getTrackState($user->fresh('formation'));

        $this->assertNull($withoutFormation->formationName);
        $this->assertSame(GhostRaceTier::NoFormation, $withoutFormation->tier);
        $this->assertSame('Standar Kelulusan CPNS', $withoutFormation->ghost->alias);
        $this->assertStringContainsString('standar kelulusan cpns', strtolower($withoutFormation->message));
    }

    public function test_forget_formation_caches_clears_track_state(): void
    {
        $formation = $this->createFormation('Dokter');
        $user = $this->createApplicant($formation, 360, 75, 90, 175);
        $this->createApplicantPool($formation, 4, 320);

        Cache::flush();

        $service = app(GhostRaceService::class);
        $service->getTrackState($user->fresh('formation'));

        $cacheKey = 'ghost_race_track_v4_'.$user->id.'_'.$formation->id.'_auto';
        $this->assertTrue(Cache::has($cacheKey));

        $service->forgetFormationCaches((int) $formation->id);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_weekly_recap_is_included_after_first_track_state(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->createAttemptForUser($user, 360, 75, 90, 175);

        $state = app(GhostRaceService::class)->getTrackState($user->fresh());

        $this->assertNotNull($state->weeklyRecap);
        $this->assertSame(0, $state->weeklyRecap->pointsGained);
    }

    public function test_select_rival_persists_choice(): void
    {
        $formation = $this->createFormation('Pranata Komputer');
        $leader = $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        Cache::flush();

        $service = app(GhostRaceService::class);
        $service->selectRival($user->fresh('formation'), (int) $leader->id);

        $state = $service->getTrackState($user->fresh('formation'));

        $this->assertSame((int) $leader->id, $state->selectedRivalUserId);
        $this->assertSame(
            'Pelamar #'.strtoupper(substr(md5((string) $leader->id), 0, 3)),
            $state->ghost->alias,
        );
        $this->assertNotEmpty($state->availableRivals);
    }

    public function test_evaluate_notification_when_gap_increases(): void
    {
        $formation = $this->createFormation('Auditor');
        $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        $user->forceFill(['ghost_race_last_seen_gap' => 1])->save();

        Cache::flush();

        app(GhostRaceService::class)->evaluateRivalGapNotification($user->fresh('formation'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => GhostRivalPulledAhead::class,
        ]);
    }

    public function test_handle_activity_completed_flashes_warning_message(): void
    {
        $formation = $this->createFormation('Auditor');
        $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        $user->forceFill(['ghost_race_last_seen_gap' => 1])->save();

        Cache::flush();

        app(GhostRaceService::class)->handleActivityCompleted($user->fresh('formation'));

        $this->assertTrue(session()->has('warning'));
        $this->assertStringContainsString('memperlebar jarak', (string) session('warning'));
    }

    public function test_evaluate_notification_returns_message_when_gap_increases(): void
    {
        $formation = $this->createFormation('Auditor');
        $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        $user->forceFill(['ghost_race_last_seen_gap' => 1])->save();

        Cache::flush();

        $message = app(GhostRaceService::class)->evaluateRivalGapNotification($user->fresh('formation'));

        $this->assertNotNull($message);
        $this->assertStringContainsString('memperlebar jarak', $message);
    }

    public function test_muted_user_does_not_receive_notification(): void
    {
        $formation = $this->createFormation('Auditor');
        $this->createApplicant($formation, 395, 90, 100, 180);
        $user = $this->createApplicant($formation, 340, 70, 85, 170);
        $this->createApplicantPool($formation, 4, 320);

        $user->forceFill([
            'ghost_race_last_seen_gap' => 1,
            'ghost_race_notifications_muted' => true,
        ])->save();

        Cache::flush();

        app(GhostRaceService::class)->evaluateRivalGapNotification($user->fresh('formation'));

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $user->id,
            'type' => GhostRivalPulledAhead::class,
        ]);
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

    private function createAttemptForUser(
        User $user,
        int $total,
        int $twk,
        int $tiu,
        int $tkp,
        ExamAttemptType $type = ExamAttemptType::Full,
    ): ExamAttempt {
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
            'attempt_type' => $type,
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
