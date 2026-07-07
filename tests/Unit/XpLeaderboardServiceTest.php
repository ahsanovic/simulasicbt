<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\AudioLearningSession;
use App\Models\User;
use App\Models\XpReward;
use App\Services\XpLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpLeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_users_by_total_xp_from_audio_and_rewards(): void
    {
        $topUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Alpha']);
        $secondUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Beta']);

        AudioLearningSession::query()->create([
            'user_id' => $topUser->id,
            'subject_code' => 'twk',
            'question_count' => 20,
            'xp_earned' => 300,
            'duration_seconds' => 900,
            'completed_at' => now(),
        ]);

        XpReward::query()->create([
            'user_id' => $topUser->id,
            'source_type' => 'test',
            'source_id' => 1,
            'amount' => 100,
        ]);

        AudioLearningSession::query()->create([
            'user_id' => $secondUser->id,
            'subject_code' => 'tiu',
            'question_count' => 10,
            'xp_earned' => 250,
            'duration_seconds' => 500,
            'completed_at' => now(),
        ]);

        $service = app(XpLeaderboardService::class);
        $data = $service->getLeaderboard($secondUser->id);

        $this->assertSame($topUser->id, $data['entries'][0]['user_id']);
        $this->assertSame(400, $data['entries'][0]['xp']);
        $this->assertSame('pejuang_akuntabel', $data['entries'][0]['devotion_badge']['value']);
        $this->assertSame(2, $data['current_user']['rank']);
        $this->assertSame(250, $data['current_user']['xp']);
        $this->assertSame(1, $service->getUserRank($topUser->id));
    }
}
