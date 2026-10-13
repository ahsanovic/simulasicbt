<?php

namespace Tests\Feature\Admin;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Models\EventSession;
use App\Models\User;
use App\Services\ExamService;
use App\Services\SkbExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * The livescore board must show the score saved at submit as soon as a
 * participant finishes, not the in-progress score of a snapshot taken a
 * moment before (which misses the answers sent together with "Selesai").
 */
class LiveScoreAfterSubmitTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    public function test_the_board_shows_the_saved_score_right_after_submit(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $session = EventSession::query()->findOrFail($ctx['attempt']->event_session_id);
        $board = fn () => collect(Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $session->event, 'session' => $session])
            ->instance()->allRows)->firstWhere('attempt_id', $ctx['attempt']->id);

        // Board open while the participant works; the last answer is not saved yet.
        $this->assertSame(0, $board()['score']);

        // "Selesai": the last answer is saved and the attempt is scored.
        $ctx['attempt']->answers()->orderBy('sort_order')->first()->update(['selected_option_id' => $ctx['firstOptionId']]);
        $submitted = app(ExamService::class)->submitAttempt($ctx['attempt']->fresh());
        $this->assertGreaterThan(0, (int) $submitted->total_score);

        $row = $board();
        $this->assertFalse($row['in_progress']);
        $this->assertSame((int) $submitted->total_score, $row['score']);
    }

    public function test_the_skb_board_shows_the_saved_score_right_after_submit(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $session = EventSession::query()->findOrFail($attempt->event_session_id);
        $board = fn () => collect(Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $session->event, 'session' => $session])
            ->instance()->allRows)->firstWhere('attempt_id', $attempt->id);

        $this->assertSame(0, $board()['score']);

        $answer = $this->skbAnswerAt($attempt, 1);
        $answer->update(['selected_option_id' => $answer->question->options->firstWhere('is_correct', true)->id]);
        $submitted = app(SkbExamService::class)->submitAttempt($attempt->fresh());
        $this->assertGreaterThan(0, (int) $submitted->total_score);

        $row = $board();
        $this->assertFalse($row['in_progress']);
        $this->assertSame((int) $submitted->total_score, $row['score']);
    }

    public function test_a_cache_failure_while_refreshing_the_board_does_not_undo_the_submit(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        Cache::partialMock()->shouldReceive('add')->andThrow(new RuntimeException('Redis timeout'));

        $submitted = app(ExamService::class)->submitAttempt($ctx['attempt']->fresh());

        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
    }
}
