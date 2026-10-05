<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Livewire\Public\LiveScoreShow;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\SkbExamService;
use App\Support\LiveScoreCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The livescore board must stay cheap with dozens of participants: scores come
 * from aggregate queries (not every answer as a model) and the board is built
 * once per request, while giving exactly the same numbers as before.
 */
class LiveScoreBoardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_stats_match_calculate_scores(): void
    {
        [, , , $attempts] = $this->createBoard();

        $stats = ExamAttempt::liveBoardStats(array_map(fn (ExamAttempt $a) => $a->id, $attempts));

        foreach ($attempts as $attempt) {
            $expected = $attempt->fresh()->calculateScores();
            $live = $stats[$attempt->id];

            $this->assertSame($expected['twk'], $live['twk']);
            $this->assertSame($expected['tiu'], $live['tiu']);
            $this->assertSame($expected['tkp'], $live['tkp']);
            $this->assertSame($expected['total'], $live['score']);
            $this->assertSame($attempt->answers()->count(), $live['total']);
            $this->assertSame($attempt->answers()->whereNotNull('selected_option_id')->count(), $live['answered']);
        }

        // The fixture really exercises each subject, and the deleted question earns nothing.
        $this->assertSame(['total' => 4, 'answered' => 4, 'twk' => 5, 'tiu' => 0, 'tkp' => 4, 'score' => 9], $stats[$attempts[0]->id]);
        $this->assertSame(['total' => 4, 'answered' => 0, 'twk' => 0, 'tiu' => 0, 'tkp' => 0, 'score' => 0], $stats[$attempts[1]->id]);
    }

    public function test_board_stats_handle_no_attempts(): void
    {
        $this->assertSame([], ExamAttempt::liveBoardStats([]));
    }

    public function test_admin_board_shows_live_scores_from_a_shared_snapshot(): void
    {
        [$admin, $event, $session, $attempts] = $this->createBoard();

        $component = Livewire::actingAs($admin)->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $row = collect($component->get('rows'))->firstWhere('attempt_id', $attempts[0]->id);
        $this->assertSame(4, $row['answered']);
        $this->assertSame(9, $row['score']);

        // Further refreshes (this screen or any other) reuse the snapshot...
        $this->assertSame(0, $this->answerQueriesDuring(fn () => $component->call('pollBoard')));
        $this->assertSame(0, $this->answerQueriesDuring(fn () => Livewire::actingAs($admin)->test(LiveScore::class, ['event' => $event, 'session' => $session])));

        // ...until attempts change (add time / reset bust it): then it is
        // rebuilt once, with the 2 aggregate queries.
        LiveScoreCache::bust($event->id);
        $this->assertSame(2, $this->answerQueriesDuring(fn () => $component->call('pollBoard')));
    }

    public function test_snapshot_expires_after_its_ttl(): void
    {
        [$admin, $event, $session, $attempts] = $this->createBoard();
        $component = Livewire::actingAs($admin)->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $this->travel(LiveScoreCache::TTL_SECONDS + 1)->seconds();

        $this->assertSame(2, $this->answerQueriesDuring(fn () => $component->call('pollBoard')));
    }

    public function test_changing_a_participant_refreshes_the_board_at_once(): void
    {
        [$admin, $event, $session] = $this->createBoard();
        $component = Livewire::actingAs($admin)->test(LiveScore::class, ['event' => $event, 'session' => $session]);
        $this->assertCount(2, $component->get('rows'));

        EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id,
            'user_id' => User::factory()->create(['role' => UserRole::Peserta])->id,
            'name' => 'Peserta Baru', 'nik' => '3201010101019998', 'jabatan_label' => 'x',
        ]);

        $this->assertGreaterThan(0, $this->answerQueriesDuring(fn () => $component->call('pollBoard')));
    }

    public function test_skb_board_stats_match_the_live_score_rule(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::query()->create(['name' => 'SKB Board', 'status' => EventStatus::Active, 'created_by' => $admin->id]);
        $session = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi 1', 'code' => 'SKB123', 'status' => EventStatus::Active]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $peserta->id,
            'name' => 'Peserta SKB', 'nik' => '3201010101019997', 'jabatan_label' => 'Analis', 'jabatan_skb_id' => $jabatan->id,
        ]);
        $attempt = SkbExamAttempt::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'event_participant_id' => $participant->id,
            'user_id' => $peserta->id,
            'jabatan_skb_id' => $jabatan->id,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::InProgress,
            'correct_score' => 5,
        ]);

        foreach ([[true, true], [true, false], [false, null]] as $i => [$answered, $correct]) {
            $question = SkbQuestion::query()->create(['jabatan_skb_id' => $jabatan->id, 'content' => 'Soal', 'difficulty' => 'medium', 'is_active' => true, 'created_by' => $admin->id]);
            $right = $question->options()->create(['label' => 'A', 'content_type' => QuestionOptionContentType::Text, 'content' => 'A', 'is_correct' => true, 'sort_order' => 1]);
            $wrong = $question->options()->create(['label' => 'B', 'content_type' => QuestionOptionContentType::Text, 'content' => 'B', 'is_correct' => false, 'sort_order' => 2]);
            $attempt->answers()->create([
                'skb_question_id' => $question->id,
                'sort_order' => $i + 1,
                'selected_option_id' => $answered ? ($correct ? $right->id : $wrong->id) : null,
            ]);
        }

        $stats = SkbExamAttempt::liveBoardStats([$attempt->id]);
        $legacy = app(SkbExamService::class)->liveScores([$attempt->load('answers')]);

        $this->assertSame(['total' => 3, 'answered' => 2, 'benar' => 1], $stats[$attempt->id]);
        $this->assertSame($legacy[$attempt->id]['benar'], $stats[$attempt->id]['benar']);
    }

    public function test_public_board_rows_are_compact(): void
    {
        [, $event] = $this->createBoard();
        $event->update(['public_livescore' => true]);

        $html = Livewire::test(LiveScoreShow::class, ['event' => $event])->html();

        $this->assertSame(2, substr_count($html, 'class="ls-row'));
        // Short class names instead of long utility lists per row: well under 1.5 KB a row.
        $rows = array_slice(explode('<div wire:key="skd-', strstr($html, '</main>', true)), 1);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertLessThan(1500, strlen($row));
        }
    }

    private function answerQueriesDuring(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $count = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'exam_answers'))->count();
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Two participants: one answered a correct TWK, a wrong TIU, a TKP option
     * worth 4 and a question that was later deleted; the other answered nothing.
     *
     * @return array{0: User, 1: Event, 2: EventSession, 3: list<ExamAttempt>}
     */
    private function createBoard(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = Exam::query()->create([
            'title' => 'Simulasi Board',
            'slug' => 'simulasi-board',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);
        $event = Event::query()->create(['name' => 'Tryout Board', 'exam_id' => $exam->id, 'status' => EventStatus::Active, 'created_by' => $admin->id]);
        $session = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi 1', 'code' => 'BRD123', 'status' => EventStatus::Active]);

        $make = function (SubjectCode $code): Question {
            $subject = Subject::query()->firstOrCreate(['code' => $code], ['name' => $code->label(), 'slug' => $code->value, 'sort_order' => 1]);
            $material = Material::query()->firstOrCreate(['subject_id' => $subject->id], ['slug' => 'materi-'.$code->value, 'name' => 'Materi', 'sort_order' => 1]);

            $question = Question::query()->create(['subject_id' => $subject->id, 'material_id' => $material->id, 'content' => 'Soal', 'difficulty' => 'easy', 'is_active' => true]);

            foreach ([['A', true, 4], ['B', false, 2]] as $i => [$label, $correct, $weight]) {
                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'label' => $label,
                    'content' => 'Opsi '.$label,
                    'is_correct' => $code === SubjectCode::Tkp ? false : $correct,
                    'score_weight' => $code === SubjectCode::Tkp ? $weight : null,
                    'sort_order' => $i + 1,
                ]);
            }

            return $question;
        };

        // [question, option label picked by participant 1]
        $plan = [
            [$make(SubjectCode::Twk), 'A'],
            [$make(SubjectCode::Tiu), 'B'],
            [$make(SubjectCode::Tkp), 'A'],
            [$make(SubjectCode::Twk), 'A'],
        ];

        $attempts = [];

        foreach ([0, 1] as $p) {
            $attempt = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'event_id' => $event->id,
                'event_session_id' => $session->id,
                'user_id' => User::factory()->create(['role' => UserRole::Peserta])->id,
                'started_at' => now()->subMinutes(5),
                'expires_at' => now()->addMinutes(60),
                'status' => ExamAttemptStatus::InProgress,
            ]);

            foreach ($plan as $order => [$question, $label]) {
                ExamAnswer::query()->create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'sort_order' => $order + 1,
                    'selected_option_id' => $p === 0 ? $question->options()->where('label', $label)->value('id') : null,
                ]);
            }

            $attempts[] = $attempt;
        }

        $plan[3][0]->delete();

        return [$admin, $event, $session, $attempts];
    }
}
