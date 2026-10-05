<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use App\Models\Question;
use App\Models\SkbExamAnswer;
use App\Models\SkbQuestion;
use App\Services\SkbExamService;
use App\Support\ExamQuestionCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Exam room state cannot be tampered with from the browser, submitting is a
 * fixed number of queries, and question content comes from a cache that
 * follows admin edits.
 */
class ExamRoomHardeningTest extends ExamDeadlineEnforcementTest
{
    public function test_skb_attempt_id_cannot_be_changed_from_the_browser(): void
    {
        [$user] = $this->startSkbAttempt();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(SkbExamRoom::class)->set('attemptId', 999999);
    }

    public function test_skb_answer_states_cannot_be_changed_from_the_browser(): void
    {
        [$user] = $this->startSkbAttempt();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(SkbExamRoom::class)->set('answerStates.0.question_id', 1);
    }

    public function test_skd_valid_options_cannot_be_changed_from_the_browser(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])->set('currentOptionIds', [1, 2, 3]);
    }

    public function test_skd_answer_states_cannot_be_changed_from_the_browser(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])->set('answerStates.0.question_id', 1);
    }

    public function test_skb_submit_scores_like_the_correct_option_rule_in_a_fixed_number_of_queries(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $answers = collect([1, 2, 3])->map(fn (int $sortOrder) => $this->skbAnswerAt($attempt, $sortOrder));

        // Question 3 gets a second option flagged correct after A: only the
        // first correct option in option order counts (SkbQuestion::correctOption).
        $answers[2]->question->options()->where('label', 'B')->update(['is_correct' => true]);

        $pick = fn (SkbExamAnswer $answer, string $label) => $answer->question->options()->where('label', $label)->value('id');
        SkbExamAnswer::query()->whereKey($answers[0]->id)->update(['selected_option_id' => $pick($answers[0], 'A')]); // correct
        SkbExamAnswer::query()->whereKey($answers[1]->id)->update(['selected_option_id' => $pick($answers[1], 'C')]); // wrong
        SkbExamAnswer::query()->whereKey($answers[2]->id)->update(['selected_option_id' => $pick($answers[2], 'B')]); // 2nd "correct": not counted

        DB::enableQueryLog();
        $submitted = app(SkbExamService::class)->submitAttempt($attempt);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $expected = $answers->map(function (SkbExamAnswer $answer) {
            $answer->refresh();
            $correct = SkbQuestion::query()->with('options')->find($answer->skb_question_id)->correctOption();

            return $answer->selected_option_id !== null && $answer->selected_option_id === $correct?->id;
        });

        $this->assertSame([true, false, false], $answers->map(fn (SkbExamAnswer $a) => (bool) $a->fresh()->is_correct)->all());
        $this->assertSame($expected->all(), [true, false, false]);
        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame(1, $submitted->correct_count);
        $this->assertSame(5, $submitted->total_score);
        $this->assertLessThanOrEqual(8, $queries, 'Submitting must not run one query per answer.');
    }

    public function test_skd_question_content_comes_from_cache_and_follows_admin_edits(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $questionId = $ctx['attempt']->answers()->where('sort_order', 1)->value('question_id');

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])->assertSee('Soal nomor 1?');

        // Second participant / refresh: no question queries any more.
        DB::enableQueryLog();
        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])->assertSee('Soal nomor 1?');
        $questionQueries = collect(DB::getQueryLog())->filter(fn (array $q) => preg_match('/from [`"]questions[`"]/', $q['query']))->count();
        DB::disableQueryLog();
        $this->assertSame(0, $questionQueries);

        // The admin fixes a typo: the room shows the new text at once.
        Question::query()->find($questionId)->update(['content' => 'Soal nomor 1 (revisi)?']);

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])->assertSee('Soal nomor 1 (revisi)?');
    }

    public function test_question_cache_works_with_a_serializing_cache_store(): void
    {
        // Production stores (Redis, database) serialize values and refuse to
        // unserialize PHP objects (cache.serializable_classes = false). Make
        // the test store behave the same, so caching a model would fail here.
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');

        [$user, $attempt] = $this->startSkbAttempt();
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $skbId = $this->skbAnswerAt($attempt, 1)->skb_question_id;
        $skdId = $ctx['attempt']->answers()->where('sort_order', 1)->value('question_id');

        foreach ([1, 2] as $read) { // first read fills the cache, second comes from it
            $skb = ExamQuestionCache::skb($skbId);
            $skd = ExamQuestionCache::skd($skdId);
        }

        $this->assertInstanceOf(SkbQuestion::class, $skb);
        $this->assertSame('A', $skb->correctOption()->label);
        $this->assertInstanceOf(Question::class, $skd);
        $this->assertSame('twk', $skd->subject->code->value);
        $this->assertCount(2, $skd->options);

        Livewire::actingAs($user)->test(SkbExamRoom::class)->assertSee('Opsi A');
    }

    public function test_skb_question_content_follows_option_edits(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->skbAnswerAt($attempt, 1);

        Livewire::actingAs($user)->test(SkbExamRoom::class)->assertSee('Opsi A');

        $first->question->options()->where('label', 'A')->first()->update(['content' => 'Opsi A diperbaiki']);

        Livewire::actingAs($user)->test(SkbExamRoom::class)->assertSee('Opsi A diperbaiki');
    }

    public function test_skb_room_shows_the_new_question_after_next(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $second = $this->skbAnswerAt($attempt, 2);

        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->set('selectedOptionId', $this->skbAnswerAt($attempt, 1)->question->options->first()->id)
            ->call('next')
            ->assertSet('currentIndex', 1)
            ->assertSee(strip_tags($second->question->content));
    }
}
