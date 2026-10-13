<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Services\ModeUjianRoom\ClientRoomFinisher;
use App\Services\ModeUjianRoom\DeviceToken;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Support\ExamDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * "Selesai Ujian" / time up in the browser room: the unsent answers are saved
 * and the attempt is scored with the existing rules in one transaction.
 */
class ModeUjianRoomFinisherTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    private function finish(array $ctx, bool $timeUp, ?string $token = null): array
    {
        $answer = $ctx['attempt']->answers()->orderBy('sort_order')->first();

        return app(ClientRoomFinisher::class)->finish(
            RoomKinds::get('skd'),
            $ctx['attempt']->fresh(),
            $token ?? $ctx['token'],
            [['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 4]],
            $timeUp,
        );
    }

    private function skd(): array
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['token'] = DeviceToken::issue($ctx['attempt']);

        return $ctx;
    }

    public function test_finishing_saves_the_last_answers_then_scores_with_the_existing_rules(): void
    {
        $ctx = $this->skd();

        $result = $this->finish($ctx, timeUp: false);

        $attempt = $ctx['attempt']->fresh();
        $this->assertSame('submitted', $result['status']);
        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->status);
        $this->assertSame($ctx['firstOptionId'], $attempt->answers()->orderBy('sort_order')->first()->selected_option_id);
        $this->assertSame($attempt->calculateScores()['total'], (int) $attempt->total_score);
        $this->assertGreaterThan(0, (int) $attempt->total_score);
    }

    public function test_time_up_while_the_admin_added_time_saves_but_does_not_submit(): void
    {
        $ctx = $this->skd();

        $result = $this->finish($ctx, timeUp: true);

        $this->assertSame('extended', $result['status']);
        $this->assertSame(ExamAttemptStatus::InProgress, $ctx['attempt']->fresh()->status);
        $this->assertSame($ctx['firstOptionId'], $ctx['attempt']->answers()->orderBy('sort_order')->first()->selected_option_id);
    }

    public function test_time_up_within_the_grace_saves_the_answers_and_submits(): void
    {
        $ctx = $this->skd();
        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS - 2));

        $result = $this->finish($ctx, timeUp: true);

        $this->assertSame('submitted', $result['status']);
        $this->assertSame($ctx['firstOptionId'], $ctx['attempt']->answers()->orderBy('sort_order')->first()->selected_option_id);
    }

    public function test_late_answers_past_the_grace_are_left_out_but_the_attempt_still_closes(): void
    {
        $ctx = $this->skd();
        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS + 5));

        $result = $this->finish($ctx, timeUp: true);

        $this->assertSame('submitted', $result['status']);
        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        $this->assertNull($ctx['attempt']->answers()->orderBy('sort_order')->first()->selected_option_id);
    }

    public function test_an_old_device_cannot_finish(): void
    {
        $ctx = $this->skd();
        DeviceToken::issue($ctx['attempt']);

        $result = $this->finish($ctx, timeUp: false);

        $this->assertSame('device', $result['status']);
        $this->assertSame(ExamAttemptStatus::InProgress, $ctx['attempt']->fresh()->status);
    }

    public function test_finishing_twice_returns_the_submitted_attempt_unchanged(): void
    {
        $ctx = $this->skd();
        $this->finish($ctx, timeUp: false);
        $score = (int) $ctx['attempt']->fresh()->total_score;

        $result = $this->finish($ctx, timeUp: false);

        $this->assertSame('submitted', $result['status']);
        $this->assertSame($score, (int) $ctx['attempt']->fresh()->total_score);
    }

    public function test_skb_finish_counts_correct_answers_with_the_existing_rule(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $token = DeviceToken::issue($attempt);
        $answer = $this->skbAnswerAt($attempt, 1);
        $correct = $answer->question->options->firstWhere('is_correct', true)->id;

        $result = app(ClientRoomFinisher::class)->finish(RoomKinds::get('skb'), $attempt->fresh(), $token, [
            ['id' => $answer->id, 'option_id' => $correct, 'is_marked' => false, 'version' => 1],
        ], false);

        $this->assertSame('submitted', $result['status']);
        $this->assertSame(1, (int) $attempt->fresh()->correct_count);
    }
}
