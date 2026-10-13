<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Services\ModeUjianRoom\AnswerBatchWriter;
use App\Services\ModeUjianRoom\DeviceReplaced;
use App\Services\ModeUjianRoom\DeviceToken;
use App\Services\ModeUjianRoom\RoomClosed;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Support\ExamDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * Answers sent by the browser exam room are saved only when newer than the
 * stored ones, only for this attempt's questions and options, only while the
 * attempt takes answers, and only from the device that opened the room last.
 */
class ModeUjianAnswerBatchWriterTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    private function skd(): array
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['token'] = DeviceToken::issue($ctx['attempt']);
        $ctx['answers'] = $ctx['attempt']->answers()->orderBy('sort_order')->get();

        return $ctx;
    }

    private function write(array $ctx, array $answers, ?string $token = null): array
    {
        return app(AnswerBatchWriter::class)->write(RoomKinds::get('skd'), $ctx['attempt']->fresh(), $token ?? $ctx['token'], $answers);
    }

    public function test_it_saves_the_option_the_mark_and_the_version(): void
    {
        $ctx = $this->skd();
        $answer = $ctx['answers'][0];

        $result = $this->write($ctx, [['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => true, 'version' => 5]]);

        $this->assertSame(['saved' => [$answer->id], 'stale' => []], $result);
        $stored = $answer->fresh();
        $this->assertSame($ctx['firstOptionId'], $stored->selected_option_id);
        $this->assertTrue((bool) $stored->is_marked);
        $this->assertSame(5, (int) $stored->answer_version);
        $this->assertNotNull($stored->answered_at);
    }

    public function test_an_older_version_is_ignored_as_stale(): void
    {
        $ctx = $this->skd();
        $answer = $ctx['answers'][0];
        $answer->forceFill(['answer_version' => 9])->save(); // answer_version is not fillable

        $result = $this->write($ctx, [['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 8]]);

        $this->assertSame(['saved' => [], 'stale' => [$answer->id]], $result);
        $this->assertNull($answer->fresh()->selected_option_id);
    }

    public function test_the_same_answer_twice_in_a_batch_keeps_the_highest_version(): void
    {
        $ctx = $this->skd();
        $answer = $ctx['answers'][0];
        $other = $ctx['answers'][0]->question->options()->where('id', '!=', $ctx['firstOptionId'])->value('id');

        $this->write($ctx, [
            ['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 12],
            ['id' => $answer->id, 'option_id' => $other, 'is_marked' => false, 'version' => 11],
        ]);

        $this->assertSame($ctx['firstOptionId'], $answer->fresh()->selected_option_id);
        $this->assertSame(12, (int) $answer->fresh()->answer_version);
    }

    public function test_clearing_an_answer_clears_answered_at(): void
    {
        $ctx = $this->skd();
        $answer = $ctx['answers'][0];
        $this->write($ctx, [['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 1]]);

        $this->write($ctx, [['id' => $answer->id, 'option_id' => null, 'is_marked' => false, 'version' => 2]]);

        $this->assertNull($answer->fresh()->selected_option_id);
        $this->assertNull($answer->fresh()->answered_at);
    }

    public function test_an_answer_outside_the_attempt_is_rejected(): void
    {
        $ctx = $this->skd();

        $this->expectException(ValidationException::class);

        // Any id that is not one of this attempt's answers (another attempt's, or none at all).
        $this->write($ctx, [['id' => 999999, 'option_id' => null, 'is_marked' => false, 'version' => 1]]);
    }

    public function test_an_option_of_another_question_is_rejected(): void
    {
        $ctx = $this->skd();
        $secondQuestionOption = $ctx['answers'][1]->question->options()->value('id');

        $this->expectException(ValidationException::class);

        $this->write($ctx, [['id' => $ctx['answers'][0]->id, 'option_id' => $secondQuestionOption, 'is_marked' => false, 'version' => 1]]);
    }

    public function test_answers_after_the_deadline_and_grace_are_refused(): void
    {
        $ctx = $this->skd();
        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS + 1));

        $this->expectException(RoomClosed::class);

        $this->write($ctx, [['id' => $ctx['answers'][0]->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 1]]);
    }

    public function test_a_submitted_attempt_is_refused(): void
    {
        $ctx = $this->skd();
        $ctx['attempt']->update(['status' => ExamAttemptStatus::Submitted]);

        $this->expectException(RoomClosed::class);

        $this->write($ctx, [['id' => $ctx['answers'][0]->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 1]]);
    }

    public function test_an_old_device_cannot_write_after_the_room_was_opened_elsewhere(): void
    {
        $ctx = $this->skd();
        $oldToken = $ctx['token'];
        DeviceToken::issue($ctx['attempt']);

        try {
            $this->write($ctx, [['id' => $ctx['answers'][0]->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 99]], $oldToken);
            $this->fail('An old device token must be refused.');
        } catch (DeviceReplaced) {
            $this->assertNull($ctx['answers'][0]->fresh()->selected_option_id);
        }
    }

    public function test_skb_answers_are_saved(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $token = DeviceToken::issue($attempt);
        $answer = $this->skbAnswerAt($attempt, 1);
        $optionId = $answer->question->options->first()->id;

        $result = app(AnswerBatchWriter::class)->write(RoomKinds::get('skb'), $attempt->fresh(), $token, [
            ['id' => $answer->id, 'option_id' => $optionId, 'is_marked' => true, 'version' => 3],
        ]);

        $this->assertSame([$answer->id], $result['saved']);
        $this->assertSame($optionId, $answer->fresh()->selected_option_id);
        $this->assertTrue((bool) $answer->fresh()->is_marked);
    }

    public function test_a_retry_of_an_answer_already_saved_counts_as_saved(): void
    {
        $ctx = $this->skd();
        $answer = $ctx['answers'][0];
        $sent = [['id' => $answer->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 5]];
        $this->write($ctx, $sent);

        // The first response was lost on the way back; the browser sends it again.
        $result = $this->write($ctx, $sent);

        $this->assertSame(['saved' => [$answer->id], 'stale' => []], $result);
    }

    public function test_a_batch_after_an_outage_is_written_in_one_query(): void
    {
        $ctx = $this->skd();
        [$first, $second] = [$ctx['answers'][0], $ctx['answers'][1]];
        $secondOption = $second->question->options()->value('id');

        DB::enableQueryLog();
        $result = $this->write($ctx, [
            ['id' => $first->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => true, 'version' => 6],
            ['id' => $second->id, 'option_id' => $secondOption, 'is_marked' => false, 'version' => 7],
        ]);
        $updates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => preg_match('/^update [`"]exam_answers[`"]/i', $sql));
        DB::disableQueryLog();

        $this->assertCount(1, $updates, 'One UPDATE for the whole batch, however many answers it holds.');
        $this->assertSame(['saved' => [$first->id, $second->id], 'stale' => []], $result);
        $this->assertSame($ctx['firstOptionId'], $first->fresh()->selected_option_id);
        $this->assertTrue((bool) $first->fresh()->is_marked);
        $this->assertNotNull($first->fresh()->answered_at);
        $this->assertSame(6, (int) $first->fresh()->answer_version);
        $this->assertSame($secondOption, $second->fresh()->selected_option_id);
        $this->assertFalse((bool) $second->fresh()->is_marked);
        $this->assertSame(7, (int) $second->fresh()->answer_version);
    }

    public function test_a_skb_batch_is_written_in_one_query_and_skips_older_versions(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $token = DeviceToken::issue($attempt);
        $answers = collect([1, 2, 3])->map(fn (int $order) => $this->skbAnswerAt($attempt, $order));
        $answers[2]->forceFill(['answer_version' => 9])->save(); // a newer copy is already stored

        DB::enableQueryLog();
        $result = app(AnswerBatchWriter::class)->write(RoomKinds::get('skb'), $attempt->fresh(), $token, $answers->values()->map(fn ($answer, int $i) => [
            'id' => $answer->id, 'option_id' => $answer->question->options->first()->id, 'is_marked' => $i === 0, 'version' => $i + 1,
        ])->all());
        $updates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => preg_match('/^update [`"]skb_exam_answers[`"]/i', $sql));
        DB::disableQueryLog();

        $this->assertCount(1, $updates);
        $this->assertSame(['saved' => [$answers[0]->id, $answers[1]->id], 'stale' => [$answers[2]->id]], $result);
        $this->assertSame($answers[0]->question->options->first()->id, $answers[0]->fresh()->selected_option_id);
        $this->assertSame($answers[1]->question->options->first()->id, $answers[1]->fresh()->selected_option_id);
        $this->assertTrue((bool) $answers[0]->fresh()->is_marked);
        $this->assertFalse((bool) $answers[1]->fresh()->is_marked);
        $this->assertNull($answers[2]->fresh()->selected_option_id);
        $this->assertSame(9, (int) $answers[2]->fresh()->answer_version);
    }
}
