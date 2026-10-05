<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
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

    public function test_admin_board_shows_live_scores_and_builds_once_per_request(): void
    {
        [$admin, $event, $session, $attempts] = $this->createBoard();

        $component = Livewire::actingAs($admin)->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $row = collect($component->get('rows'))->firstWhere('attempt_id', $attempts[0]->id);
        $this->assertSame(4, $row['answered']);
        $this->assertSame(9, $row['score']);

        DB::enableQueryLog();
        $component->call('pollBoard');
        $answerQueries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'exam_answers'))->count();

        $this->assertSame(2, $answerQueries, 'The board should be built once per request (2 aggregate queries).');
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
