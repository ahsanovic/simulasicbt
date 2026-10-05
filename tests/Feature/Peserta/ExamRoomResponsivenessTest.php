<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Keeps the exam rooms light under load (dozens of participants at once):
 * picking an answer must not need a server round trip, and the background
 * deadline poll must not re-render the whole page.
 */
class ExamRoomResponsivenessTest extends ExamDeadlineEnforcementTest
{
    public function test_skb_deadline_poll_does_not_rerender_the_page(): void
    {
        [$user] = $this->startSkbAttempt();

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class)->call('checkExpiry');

        $this->assertArrayNotHasKey('html', $this->effects($component));
        $component->assertDispatched('exam-deadline-synced');
    }

    public function test_skd_deadline_poll_does_not_rerender_the_page(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $component = Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->call('checkExpiry');

        $this->assertArrayNotHasKey('html', $this->effects($component));
        $component->assertDispatched('exam-deadline-synced');
    }

    public function test_time_up_still_renders_the_time_up_screen(): void
    {
        [$user] = $this->startSkbAttempt();
        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        $this->travel(61)->minutes();

        $component->call('checkExpiry');
        $this->assertArrayHasKey('html', $this->effects($component));
        $component->assertSee('Waktu Ujian Habis');
    }

    public function test_skb_options_are_picked_in_the_browser_and_saved_with_next(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSeeHtml('wire:model="selectedOptionId"')
            ->assertDontSeeHtml('selectOption(')
            ->assertSeeHtml('examDeadlinePoll(30000)')
            ->assertDontSeeHtml('wire:poll');

        // Deferred wire:model: the pick arrives with the next action.
        $component->set('selectedOptionId', $optionId)->call('next');

        $this->assertSame($optionId, $first->fresh()->selected_option_id);
        $component->assertSet('currentIndex', 1);
    }

    public function test_skb_pick_from_another_question_is_ignored(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);
        $foreign = $this->skbAnswerAt($attempt, 2)->question->options->first()->id;

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->set('selectedOptionId', $foreign)
            ->call('next');

        $this->assertNull($first->fresh()->selected_option_id);
    }

    public function test_skb_exposes_saved_state_for_the_browser(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $optionId = $this->skbAnswerAt($attempt, 3)->question->options->first()->id;

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSet('unansweredSaved', 3)
            ->assertSet('savedOptionId', null)
            ->call('goToQuestion', 2)
            ->set('selectedOptionId', $optionId)
            ->call('saveAnswer')
            ->assertSet('savedOptionId', $optionId)
            ->assertSet('unansweredSaved', 2);
    }

    public function test_skd_options_are_picked_in_the_browser_and_saved_with_next(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $component = Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSeeHtml('wire:model="selectedOptionId"')
            ->assertDontSeeHtml('selectOption(')
            ->assertSeeHtml('examDeadlinePoll(30000)')
            ->assertDontSeeHtml('wire:poll');

        $component->set('selectedOptionId', $ctx['firstOptionId'])->call('next');

        $this->assertDatabaseHas('exam_answers', [
            'exam_attempt_id' => $ctx['attempt']->id,
            'sort_order' => 1,
            'selected_option_id' => $ctx['firstOptionId'],
        ]);
        $component->assertSet('savedOptionId', null)->assertSet('currentIndex', 1);
    }

    public function test_skd_resumes_at_first_unanswered_question_after_reconnect(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['attempt']->answers()->where('sort_order', 1)->update(['selected_option_id' => $ctx['firstOptionId']]);

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSet('currentIndex', 1)
            ->assertSet('selectedOptionId', null)
            ->assertSeeHtml('data-exam-connection');
    }

    public function test_skb_resumes_at_first_unanswered_question_after_reconnect(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();

        foreach ([1, 2] as $sortOrder) {
            $answer = $this->skbAnswerAt($attempt, $sortOrder);
            $answer->update(['selected_option_id' => $answer->question->options->first()->id]);
        }

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSet('currentIndex', 2)
            ->assertSet('selectedOptionId', null)
            ->assertSeeHtml('data-exam-connection');
    }

    public function test_skb_resumes_at_first_question_when_all_answered(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);

        foreach ([1, 2, 3] as $sortOrder) {
            $answer = $this->skbAnswerAt($attempt, $sortOrder);
            $answer->update(['selected_option_id' => $answer->question->options->first()->id]);
        }

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSet('currentIndex', 0)
            ->assertSet('selectedOptionId', $first->question->options->first()->id);
    }

    public function test_skb_already_submitted_attempt_goes_to_result_not_time_up(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        // "Selesai" went through but its response was lost; the room is still open.
        $attempt->update(['status' => ExamAttemptStatus::Submitted, 'submitted_at' => now()]);

        $component->call('checkExpiry')
            ->assertSet('timeUp', false)
            ->assertRedirect(route('peserta.mode-ujian.skb-result', $attempt->id));

        $component->call('submitExam')
            ->assertSet('timeUp', false)
            ->assertRedirect(route('peserta.mode-ujian.skb-result', $attempt->id));
    }

    public function test_skd_already_submitted_attempt_goes_to_result_not_time_up(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        $ctx['attempt']->update(['status' => ExamAttemptStatus::Submitted, 'submitted_at' => now()]);

        $component->call('next')
            ->assertSet('timeUp', false)
            ->assertRedirect(route('peserta.mode-ujian.skd-result', $ctx['attempt']->id));
    }

    public function test_attempt_closed_after_deadline_still_shows_time_up(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        $attempt->update(['status' => ExamAttemptStatus::Submitted, 'expires_at' => now()->subMinute()]);

        $component->call('checkExpiry')->assertSet('timeUp', true);
    }

    public function test_save_buttons_show_saving_state(): void
    {
        [$user] = $this->startSkbAttempt();

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSeeHtml('<span wire:loading wire:target="next">Menyimpan…</span>');
    }

    /** @return array<string, mixed> */
    private function effects(Testable $component): array
    {
        return (fn () => $this->lastState->getEffects())->call($component);
    }
}
