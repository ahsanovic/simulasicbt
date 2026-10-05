<?php

namespace Tests\Feature\Peserta;

use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use App\Models\ExamAnswer;
use App\Models\QuestionOption;
use App\Models\SkbExamAnswer;
use App\Services\SkbExamService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Answer versioning (X-Exam-Seq): the last save the participant made always
 * wins, whatever order the requests reach the server in.
 */
class AnswerVersioningTest extends ExamDeadlineEnforcementTest
{
    public function test_skb_out_of_order_saves_keep_the_newest_version(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $answer = $this->skbAnswerAt($attempt, 1);
        $options = $answer->question->options->pluck('id')->values(); // A, B, C, D
        $service = app(SkbExamService::class);

        // Picks A(v1) B(v2) C(v3) D(v4) arrive as v3, v1, v4, v2.
        $this->assertTrue($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $options[2], 3));
        $this->assertNull($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $options[0], 1));
        $this->assertTrue($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $options[3], 4));
        $this->assertNull($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $options[1], 2));

        $answer->refresh();
        $this->assertSame($options[3], $answer->selected_option_id);
        $this->assertSame(4, $answer->answer_version);
    }

    public function test_skb_duplicate_request_is_harmless(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $answer = $this->skbAnswerAt($attempt, 1);
        $option = $answer->question->options->first()->id;
        $service = app(SkbExamService::class);

        $this->assertTrue($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $option, 5));
        $this->assertNull($service->saveAnswer($attempt->fresh(), $answer->skb_question_id, $option, 5));

        $this->assertSame($option, $answer->fresh()->selected_option_id);
        $this->assertSame(5, $answer->fresh()->answer_version);
    }

    public function test_skb_room_ignores_a_stale_save_that_arrives_late(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $answer = $this->skbAnswerAt($attempt, 1);
        [$a, $c] = [$answer->question->options[0]->id, $answer->question->options[2]->id];
        $room = Livewire::actingAs($user)->test(SkbExamRoom::class);

        // Both requests carry the same page state, as in the browser: the save
        // of A (seq 6) timed out, the participant picked C and saved again
        // (seq 7). C lands first, then the old A request finally arrives.
        $this->wireCall($room, ['selectedOptionId' => $c], 'next', 7);
        $stale = $this->wireCall($room, ['selectedOptionId' => $a], 'next', 6);

        $this->assertSame($c, $answer->fresh()->selected_option_id);
        $this->assertSame(7, $answer->fresh()->answer_version);

        // The stale page does not move on as if it saved: it reloads the room.
        $this->assertSame(route('peserta.mode-ujian.skb.room'), $stale['effects']['redirect'] ?? null);
        $this->assertSame(0, json_decode($stale['snapshot'], true)['data']['currentIndex']);
    }

    public function test_skd_room_ignores_a_stale_save_that_arrives_late(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $first = ExamAnswer::query()->where('exam_attempt_id', $ctx['attempt']->id)->where('sort_order', 1)->firstOrFail();
        $wrongOptionId = QuestionOption::query()->where('question_id', $first->question_id)->where('label', 'B')->value('id');
        $room = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        $this->wireCall($room, ['selectedOptionId' => $wrongOptionId], 'next', 7);
        $stale = $this->wireCall($room, ['selectedOptionId' => $ctx['firstOptionId']], 'next', 6);

        $first->refresh();
        $this->assertSame($wrongOptionId, $first->selected_option_id);
        $this->assertSame(7, $first->answer_version);
        $this->assertSame(route('peserta.exam.room', $ctx['exam']->id), $stale['effects']['redirect'] ?? null);
        $this->assertSame(0, json_decode($stale['snapshot'], true)['data']['currentIndex']);
    }

    public function test_skd_page_open_during_admin_reset_reloads_instead_of_losing_answers(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $room = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        // The proctor resets the attempt: its answer rows are recreated, so the
        // open page still points at rows that no longer exist.
        $rows = ExamAnswer::query()->where('exam_attempt_id', $ctx['attempt']->id)->get(['exam_attempt_id', 'question_id', 'sort_order']);
        ExamAnswer::query()->where('exam_attempt_id', $ctx['attempt']->id)->delete();
        $rows->each(fn (ExamAnswer $row) => ExamAnswer::query()->create($row->only(['exam_attempt_id', 'question_id', 'sort_order'])));

        $response = $this->wireCall($room, ['selectedOptionId' => $ctx['firstOptionId']], 'next', 3);

        $this->assertSame(route('peserta.exam.room', $ctx['exam']->id), $response['effects']['redirect'] ?? null);
        $this->assertSame(0, json_decode($response['snapshot'], true)['data']['currentIndex']);
    }

    public function test_skd_newer_version_replaces_older(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $first = ExamAnswer::query()->where('exam_attempt_id', $ctx['attempt']->id)->where('sort_order', 1)->firstOrFail();
        $wrongOptionId = QuestionOption::query()->where('question_id', $first->question_id)->where('label', 'B')->value('id');
        $room = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);

        $this->wireCall($room, ['selectedOptionId' => $wrongOptionId], 'next', 3);
        $this->wireCall($room, ['selectedOptionId' => $ctx['firstOptionId']], 'next', 4);

        $this->assertSame($ctx['firstOptionId'], $first->fresh()->selected_option_id);
        $this->assertSame(4, $first->fresh()->answer_version);
    }

    public function test_requests_without_a_version_still_save(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $answer = $this->skbAnswerAt($attempt, 1);
        $option = $answer->question->options->first()->id;

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->set('selectedOptionId', $option)
            ->call('next');

        $this->assertSame($option, $answer->fresh()->selected_option_id);
    }

    public function test_invalid_version_header_is_ignored(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $answer = $this->skbAnswerAt($attempt, 1);
        $option = $answer->question->options->first()->id;

        $room = Livewire::actingAs($user)->test(SkbExamRoom::class);
        $this->wireCall($room, ['selectedOptionId' => $option], 'next', '-5; drop');

        $this->assertSame($option, $answer->fresh()->selected_option_id);
        $this->assertSame(0, $answer->fresh()->answer_version);
    }

    public function test_room_exposes_the_stored_version_as_counter_floor(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        SkbExamAnswer::query()->whereKey($this->skbAnswerAt($attempt, 2)->id)->update(['answer_version' => 42]);

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertSet('answerVersionBase', 42)
            ->assertSeeHtml('data-answer-version="42"')
            ->assertSeeHtml('data-attempt-key="skb-'.$attempt->id.'"');
    }

    /**
     * A real Livewire update request carrying the browser's X-Exam-Seq header
     * and the component's page state from the initial render. (Livewire's
     * test client drops custom headers after mount, so go over HTTP.)
     */
    /** @return array{snapshot: string, effects: array<string, mixed>} the component's response */
    private function wireCall(Testable $component, array $updates, string $method, int|string $seq): array
    {
        $snapshot = (fn () => $this->lastState->getSnapshot())->call($component);

        return $this->withHeaders(['X-Livewire' => '1', 'X-Exam-Seq' => (string) $seq])
            ->postJson(app('livewire')->getUpdateUri(), [
                'components' => [[
                    'snapshot' => json_encode($snapshot),
                    'updates' => $updates,
                    'calls' => [['method' => $method, 'params' => [], 'metadata' => []]],
                ]],
            ])
            ->assertOk()
            ->json('components.0');
    }
}
