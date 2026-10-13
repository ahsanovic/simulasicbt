<?php

namespace Tests\Feature\Peserta;

use App\Models\SkbExamAnswer;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Services\ModeUjianRoom\RoomPayload;
use App\Support\ExamQuestionCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * The browser exam room gets every question once, with the saved answers,
 * and never the answer key.
 */
class ModeUjianRoomPayloadTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    public function test_skd_payload_lists_questions_in_order_with_saved_answers_and_no_answer_key(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $first = $ctx['attempt']->answers()->orderBy('sort_order')->first();
        $first->forceFill(['selected_option_id' => $ctx['firstOptionId'], 'is_marked' => true, 'answer_version' => 7])->save(); // answer_version is not fillable

        $payload = app(RoomPayload::class)->build(RoomKinds::get('skd'), $ctx['attempt'], str_repeat('t', 40));

        $this->assertSame('skd', $payload['kind']);
        $this->assertSame($ctx['attempt']->id, $payload['attemptId']);
        $this->assertSame(7, $payload['versionBase']);
        $this->assertCount(2, $payload['items']);
        $this->assertSame([1, 2], array_column($payload['items'], 'number'));
        $this->assertSame($first->id, $payload['items'][0]['id']);
        $this->assertSame($ctx['firstOptionId'], $payload['items'][0]['option_id']);
        $this->assertTrue($payload['items'][0]['marked']);
        $this->assertSame(7, $payload['items'][0]['version']);
        $this->assertSame('TWK', $payload['items'][0]['badge']);
        $this->assertStringContainsString('Soal nomor 1?', $payload['items'][0]['content']);
        $this->assertSame(['A', 'B'], array_column($payload['items'][0]['options'], 'label'));

        $json = json_encode($payload);
        $this->assertStringNotContainsString('is_correct', $json);
        $this->assertStringNotContainsString('score_weight', $json);
        $this->assertStringNotContainsString('Pembahasan', $json);
    }

    public function test_only_whitelisted_fields_ever_reach_the_browser(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        [, $skbAttempt] = $this->startSkbAttempt();

        foreach ([['skd', $ctx['attempt']], ['skb', $skbAttempt]] as [$kind, $attempt]) {
            $payload = app(RoomPayload::class)->build(RoomKinds::get($kind), $attempt, str_repeat('t', 40));

            // A new field added to the payload must be added here on purpose.
            $this->assertSame(['kind', 'attemptId', 'deviceToken', 'title', 'versionBase', 'items'], array_keys($payload));

            foreach ($payload['items'] as $item) {
                $this->assertSame(['id', 'number', 'badge', 'content', 'options', 'option_id', 'marked', 'version'], array_keys($item));

                foreach ($item['options'] as $option) {
                    $this->assertSame(['id', 'label', 'image', 'content'], array_keys($option));
                }
            }
        }
    }

    public function test_skb_payload_has_no_answer_key(): void
    {
        [, $attempt] = $this->startSkbAttempt();

        $payload = app(RoomPayload::class)->build(RoomKinds::get('skb'), $attempt, str_repeat('t', 40));

        $this->assertSame('skb', $payload['kind']);
        $this->assertCount(SkbExamAnswer::query()->where('skb_exam_attempt_id', $attempt->id)->count(), $payload['items']);
        $this->assertNull($payload['items'][0]['badge']);
        $this->assertStringNotContainsString('is_correct', json_encode($payload));
    }

    public function test_a_cold_cache_loads_all_questions_of_the_attempt_in_one_go(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        [, $skbAttempt] = $this->startSkbAttempt();
        cache()->flush();

        // Not one query set per question: 420 participants opening the room on
        // a cold cache would otherwise hit the database 110 times each.
        $skd = $this->bankQueries(fn () => app(RoomPayload::class)->build(RoomKinds::get('skd'), $ctx['attempt'], str_repeat('t', 40)));
        $skb = $this->bankQueries(fn () => app(RoomPayload::class)->build(RoomKinds::get('skb'), $skbAttempt, str_repeat('t', 40)));

        $this->assertLessThanOrEqual(5, $skd, 'questions + options + subject + material + group, once');
        $this->assertLessThanOrEqual(2, $skb, 'questions + options, once');

        // ...and the loaded questions are cached for the next participant.
        $this->assertSame(0, $this->bankQueries(fn () => app(RoomPayload::class)->build(RoomKinds::get('skd'), $ctx['attempt'], str_repeat('t', 40))));
    }

    private function bankQueries(callable $build): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $build();
        $count = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql) => preg_match('/from [`"](questions|question_options|subjects|materials|material_groups|skb_questions|skb_question_options)[`"]/', $sql))
            ->count();
        DB::disableQueryLog();

        return $count;
    }

    public function test_warm_questions_come_from_the_cache_in_one_read(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        ExamQuestionCache::warm();

        DB::enableQueryLog();
        app(RoomPayload::class)->build(RoomKinds::get('skd'), $ctx['attempt'], str_repeat('t', 40));
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(0, $queries->filter(fn (string $sql) => preg_match('/from [`"](questions|question_options)[`"]/', $sql))->count());
    }
}
