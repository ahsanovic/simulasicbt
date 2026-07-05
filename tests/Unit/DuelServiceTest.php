<?php

namespace Tests\Unit;

use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\DuelChallengeAccepted;
use App\Notifications\DuelChallengeReceived;
use App\Notifications\DuelChallengeRejected;
use App\Services\DuelQuestionGeneratorService;
use App\Services\DuelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_players_receive_identical_questions(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'lawan']);

        $duelService = app(DuelService::class);
        $result = $duelService->challengeFriend($host, 'lawan');
        $session = $duelService->acceptFriendChallenge($result->session, $opponent);

        $hostAttempt = $duelService->startPlayerAttempt($session, $host);
        $opponentAttempt = $duelService->startPlayerAttempt($session->fresh(), $opponent);

        $hostIds = $hostAttempt->answers()->orderBy('sort_order')->pluck('question_id')->all();
        $opponentIds = $opponentAttempt->answers()->orderBy('sort_order')->pluck('question_id')->all();

        $this->assertSame($hostIds, $opponentIds);
        $this->assertCount(15, $hostIds);
    }

    public function test_winner_determined_by_score_then_speed(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'lawan2']);

        $duelService = app(DuelService::class);
        $result = $duelService->challengeFriend($host, 'lawan2');
        $session = $duelService->acceptFriendChallenge($result->session, $opponent);

        $hostAttempt = $duelService->startPlayerAttempt($session, $host);
        $opponentAttempt = $duelService->startPlayerAttempt($session->fresh(), $opponent);

        foreach ($hostAttempt->answers as $answer) {
            $correct = $answer->question->options->firstWhere('is_correct', true);
            if ($correct) {
                $answer->update(['selected_option_id' => $correct->id]);
            }
        }

        $duelService->submitPlayerAttempt($session->fresh(), $host, $hostAttempt->fresh());
        $duelService->submitPlayerAttempt($session->fresh(), $opponent, $opponentAttempt->fresh());

        $session = $session->fresh();
        $this->assertSame(DuelSessionStatus::Completed, $session->status);
        $this->assertSame($host->id, $session->winner_user_id);
    }

    public function test_matchmaking_enters_queue_when_alone(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);

        $session = app(DuelService::class)->enterMatchmakingQueue($host);

        $this->assertSame(DuelSessionStatus::Waiting, $session->status);
        $this->assertFalse($session->is_bot_opponent);
        $this->assertNull($session->opponent_user_id);
    }

    public function test_matchmaking_pairs_two_players(): void
    {
        $this->seedQuestionBank();
        $playerA = User::factory()->create(['role' => UserRole::Peserta]);
        $playerB = User::factory()->create(['role' => UserRole::Peserta]);

        $duelService = app(DuelService::class);
        $queueA = $duelService->enterMatchmakingQueue($playerA);
        $session = $duelService->enterMatchmakingQueue($playerB);

        $this->assertSame($queueA->id, $session->id);
        $this->assertSame(DuelSessionStatus::InProgress, $session->status);
        $this->assertFalse($session->is_bot_opponent);
        $this->assertSame($playerA->id, $session->host_user_id);
        $this->assertSame($playerB->id, $session->opponent_user_id);
    }

    public function test_matchmaking_assigns_bot_after_timeout(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $duelService = app(DuelService::class);

        $session = $duelService->enterMatchmakingQueue($host);

        $this->travel(DuelService::MATCHMAKING_BOT_WAIT_SECONDS + 1)->seconds();

        $session = $duelService->pollMatchmaking($session, $host);

        $this->assertTrue($session->is_bot_opponent);
        $this->assertSame(DuelSessionStatus::InProgress, $session->status);
        $this->assertCount(15, $session->question_ids);
    }

    public function test_challenge_friend_waits_for_acceptance(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'lawan-wait']);

        $result = app(DuelService::class)->challengeFriend($host, 'lawan-wait');

        $this->assertSame(DuelSessionStatus::Waiting, $result->session->status);
    }

    public function test_accept_friend_challenge_starts_duel_and_notifies_host(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'lawan-accept']);

        $duelService = app(DuelService::class);
        $result = $duelService->challengeFriend($host, 'lawan-accept');
        $session = $duelService->acceptFriendChallenge($result->session, $opponent);

        $this->assertSame(DuelSessionStatus::InProgress, $session->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $host->id,
            'type' => DuelChallengeAccepted::class,
        ]);
    }

    public function test_reject_friend_challenge_notifies_host(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'lawan-reject']);

        $duelService = app(DuelService::class);
        $result = $duelService->challengeFriend($host, 'lawan-reject');
        $duelService->rejectFriendChallenge($result->session, $opponent);

        $this->assertSame(DuelSessionStatus::Cancelled, $result->session->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $host->id,
            'type' => DuelChallengeRejected::class,
        ]);
    }

    public function test_challenge_friend_notifies_opponent(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create([
            'role' => UserRole::Peserta,
            'username' => 'lawan-online',
            'last_seen_at' => now(),
        ]);

        $result = app(DuelService::class)->challengeFriend($host, 'lawan-online');

        $this->assertTrue($result->opponentWasOnline);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $opponent->id,
            'type' => DuelChallengeReceived::class,
        ]);
    }

    public function test_challenge_friend_notifies_offline_opponent_for_later(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);
        $opponent = User::factory()->create([
            'role' => UserRole::Peserta,
            'username' => 'lawan-offline',
            'last_seen_at' => now()->subHour(),
        ]);

        $result = app(DuelService::class)->challengeFriend($host, 'lawan-offline');

        $this->assertFalse($result->opponentWasOnline);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $opponent->id,
            'type' => DuelChallengeReceived::class,
        ]);
    }

    public function test_cancel_matchmaking_deletes_waiting_session(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);

        $duelService = app(DuelService::class);
        $session = $duelService->enterMatchmakingQueue($host);

        $duelService->cancelMatchmaking($host);

        $this->assertDatabaseMissing('duel_sessions', ['id' => $session->id]);
    }

    public function test_cancel_invite_code_deletes_waiting_session(): void
    {
        $this->seedQuestionBank();
        $host = User::factory()->create(['role' => UserRole::Peserta]);

        $duelService = app(DuelService::class);
        $session = $duelService->createInviteCode($host);

        $duelService->cancelInviteCode($session, $host);

        $this->assertDatabaseMissing('duel_sessions', ['id' => $session->id]);
    }

    public function test_duel_question_generator_produces_fifteen_questions(): void
    {
        $this->seedQuestionBank();

        $ids = app(DuelQuestionGeneratorService::class)->generate();

        $this->assertCount(15, $ids);
    }

    private function seedQuestionBank(): void
    {
        foreach (SubjectCode::cases() as $code) {
            $subject = Subject::query()->create([
                'code' => $code,
                'name' => $code->label(),
                'slug' => $code->value,
                'sort_order' => 1,
            ]);

            $material = Material::query()->create([
                'subject_id' => $subject->id,
                'slug' => 'materi-'.$code->value,
                'name' => 'Materi '.$code->label(),
                'sort_order' => 1,
            ]);

            $count = $code === SubjectCode::Tkp ? 6 : 6;

            for ($i = 0; $i < $count; $i++) {
                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => "Soal {$code->value} #{$i}",
                    'difficulty' => 'medium',
                    'is_active' => true,
                ]);

                if ($code === SubjectCode::Tkp) {
                    foreach (['A', 'B', 'C', 'D', 'E'] as $idx => $label) {
                        $question->options()->create([
                            'label' => $label,
                            'content' => "Opsi {$label}",
                            'score_weight' => $idx + 1,
                            'is_correct' => false,
                        ]);
                    }
                } else {
                    foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
                        $question->options()->create([
                            'label' => $label,
                            'content' => "Opsi {$label}",
                            'is_correct' => $label === 'A',
                        ]);
                    }
                }
            }
        }
    }
}
