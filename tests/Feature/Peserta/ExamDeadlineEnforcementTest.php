<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\SubjectCode;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use App\Models\ExamAnswer;
use App\Services\SkbExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * The exam room must follow the deadline stored in the database (which an
 * admin can extend from the livescore board) and must stop the peserta the
 * moment that deadline really passes.
 */
class ExamDeadlineEnforcementTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    // ---- SKB -------------------------------------------------------------

    public function test_skb_room_picks_up_time_added_by_admin_and_does_not_submit_at_old_deadline(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $attempt->update(['expires_at' => now()->addMinute()]);

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        // Admin adds 10 minutes while the peserta is working.
        $newDeadline = now()->addMinutes(11)->startOfSecond();
        $attempt->update(['expires_at' => $newDeadline]);

        // Old deadline has passed; the new one has not.
        $this->travel(2)->minutes();

        $component->call('checkExpiry')
            ->assertSet('timeUp', false)
            ->assertSet('attemptExpiresAt', $newDeadline->timestamp)
            ->assertDispatched('exam-deadline-synced', fn ($name, $params) => $params['remainingSeconds'] > 0);

        $this->assertSame(ExamAttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_skb_room_submits_and_shows_time_up_screen_when_time_runs_out(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        $this->travel(61)->minutes();

        $component->call('checkExpiry')
            ->assertSet('timeUp', true)
            ->assertSet('resultUrl', route('peserta.mode-ujian.skb-result', $attempt))
            ->assertSee('Waktu Ujian Habis');

        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_skb_room_refuses_navigation_after_deadline(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        $this->travel(61)->minutes();

        $component->call('goToQuestion', 2)
            ->assertSet('currentIndex', 0)
            ->assertSet('timeUp', true);

        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->fresh()->status);
    }

    public function test_skb_room_still_saves_an_answer_sent_just_as_time_ran_out(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->set('selectedOptionId', $optionId);

        $this->travelTo($attempt->expires_at->copy()->addSeconds(2));

        $component->call('saveAnswer');

        $this->assertSame($optionId, $first->fresh()->selected_option_id);
    }

    public function test_skb_room_rejects_an_answer_well_after_deadline(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->set('selectedOptionId', $optionId);

        $this->travelTo($attempt->expires_at->copy()->addSeconds(30));

        $component->call('saveAnswer')->assertSet('timeUp', true);

        $this->assertNull($first->fresh()->selected_option_id);
    }

    public function test_skb_submit_twice_keeps_first_submission(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $service = app(SkbExamService::class);

        $first = $service->submitAttempt($attempt);
        $this->travel(5)->minutes();
        $second = $service->submitAttempt($attempt->fresh());

        $this->assertTrue($first->submitted_at->equalTo($second->submitted_at));
    }

    // ---- SKD (ExamRoom) --------------------------------------------------

    public function test_skd_room_picks_up_time_added_by_admin_and_does_not_submit_at_old_deadline(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 1);

        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        $newDeadline = now()->addMinutes(11)->startOfSecond();
        $ctx['attempt']->update(['expires_at' => $newDeadline]);

        $this->travel(2)->minutes();

        $component->call('checkExpiry')
            ->assertSet('timeUp', false)
            ->assertSet('attemptExpiresAt', $newDeadline->timestamp)
            ->assertDispatched('exam-deadline-synced', fn ($name, $params) => $params['remainingSeconds'] > 0);

        $this->assertSame(ExamAttemptStatus::InProgress, $ctx['attempt']->fresh()->status);
    }

    public function test_skd_room_submits_and_shows_time_up_screen_when_time_runs_out(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 1);

        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        $this->travel(2)->minutes();

        $component->call('checkExpiry')
            ->assertSet('timeUp', true)
            ->assertSee('Waktu Ujian Habis');

        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        $this->assertNotNull($component->get('resultUrl'));
    }

    public function test_skd_room_refuses_navigation_and_answers_after_deadline(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 1);

        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->set('selectedOptionId', $ctx['firstOptionId']);

        $this->travel(2)->minutes();

        $component->call('next')
            ->assertSet('currentIndex', 0)
            ->assertSet('timeUp', true);

        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
        $this->assertDatabaseHas('exam_answers', [
            'exam_attempt_id' => $ctx['attempt']->id,
            'sort_order' => 1,
            'selected_option_id' => null,
        ]);
    }

    public function test_skd_room_still_saves_an_answer_sent_just_as_time_ran_out(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 1);

        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->set('selectedOptionId', $ctx['firstOptionId']);

        $this->travelTo($ctx['attempt']->expires_at->copy()->addSeconds(2));

        $component->call('next');

        $this->assertDatabaseHas('exam_answers', [
            'exam_attempt_id' => $ctx['attempt']->id,
            'sort_order' => 1,
            'selected_option_id' => $ctx['firstOptionId'],
        ]);
    }

    public function test_skd_refresh_after_time_up_scores_the_attempt_and_shows_result(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 1, modeUjian: true);
        ExamAnswer::query()
            ->where('exam_attempt_id', $ctx['attempt']->id)
            ->where('sort_order', 1)
            ->update(['selected_option_id' => $ctx['firstOptionId']]);

        $this->travel(5)->minutes();

        // Peserta reloads the exam room after the deadline passed.
        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertRedirect(route('peserta.mode-ujian.skd-result', $ctx['attempt']));

        $attempt = $ctx['attempt']->fresh();
        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->status);
        $this->assertSame(5, (int) $attempt->score_twk);
        $this->assertTrue($attempt->submitted_at->equalTo($ctx['attempt']->expires_at));
    }

    public function test_coretan_is_hidden_in_mode_ujian(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 30, modeUjian: true, subject: SubjectCode::Tiu);

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertDontSee('Coretan');
    }

    public function test_coretan_is_still_shown_outside_mode_ujian(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 30, subject: SubjectCode::Tiu);

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSee('Coretan');
    }

    public function test_content_protection_is_active_in_mode_ujian_skd_room(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 30, modeUjian: true);

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSeeHtml('data-exam-protection')
            ->assertSeeHtml('data-exam-content')
            // No screen-covering screenshot shield: remote/recording apps
            // inject PrintScreen, so it falsely accused peserta on clicks.
            ->assertDontSee('Screenshot tidak diizinkan');
    }

    public function test_content_protection_is_active_in_skb_room(): void
    {
        [$user] = $this->startSkbAttempt();

        Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->assertSeeHtml('data-exam-protection')
            ->assertSeeHtml('data-exam-content')
            // No screen-covering screenshot shield: remote/recording apps
            // inject PrintScreen, so it falsely accused peserta on clicks.
            ->assertDontSee('Screenshot tidak diizinkan');
    }

    public function test_content_protection_is_not_applied_outside_mode_ujian(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 30);

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertDontSeeHtml('data-exam-protection')
            ->assertDontSeeHtml('data-exam-content');
    }
}
