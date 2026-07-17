<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\Events\Index as PesertaEvents;
use App\Livewire\Peserta\ExamRoom;
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
use Livewire\Livewire;
use Tests\TestCase;

class OfflineEventRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_an_option_is_saved_immediately_without_navigating(): void
    {
        $ctx = $this->createInProgressSessionAttempt();

        Livewire::actingAs($ctx['user'])
            ->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->call('selectOption', $ctx['firstOptionId']);

        // No next()/submit() was called — yet the answer must already be in the DB,
        // so a sudden disconnect or power loss right after picking cannot lose it.
        $this->assertDatabaseHas('exam_answers', [
            'sort_order' => 1,
            'selected_option_id' => $ctx['firstOptionId'],
        ]);
    }

    public function test_reconnecting_restores_previously_saved_answers(): void
    {
        $ctx = $this->createInProgressSessionAttempt();

        $ctx['attempt']->answers()->where('sort_order', 1)->update([
            'selected_option_id' => $ctx['firstOptionId'],
            'answered_at' => now(),
        ]);

        $component = Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']]);
        $answerStates = $component->get('answerStates');

        $this->assertSame($ctx['firstOptionId'], $answerStates[0]['selected_option_id']);
        $this->assertSame(1, $component->get('answeredCount'));
    }

    public function test_participant_resumes_session_from_events_page_without_re_entering_code(): void
    {
        $ctx = $this->createInProgressSessionAttempt();

        Livewire::actingAs($ctx['user'])
            ->test(PesertaEvents::class)
            ->call('resume', $ctx['session']->id)
            ->assertHasNoErrors()
            ->assertRedirect(route('peserta.exam.room', $ctx['exam']));
    }

    public function test_events_page_shows_resume_banner_for_active_attempt(): void
    {
        $ctx = $this->createInProgressSessionAttempt();

        Livewire::actingAs($ctx['user'])
            ->test(PesertaEvents::class)
            ->assertSee('Ujian Sesi Sedang Berlangsung')
            ->assertSee($ctx['event']->name)
            ->assertSee('Lanjutkan Ujian');
    }

    public function test_exam_history_labels_event_attempts_with_the_event_name(): void
    {
        $ctx = $this->createInProgressSessionAttempt();

        $ctx['attempt']->update([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'score_twk' => 0,
            'score_tiu' => 0,
            'score_tkp' => 0,
            'total_score' => 0,
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee($ctx['event']->name)
            ->assertSee('Event Offline');
    }

    /**
     * @return array{user: User, event: Event, session: EventSession, exam: Exam, attempt: ExamAttempt, firstOptionId: int}
     */
    private function createInProgressSessionAttempt(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Event',
            'slug' => 'simulasi-event',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Offline Test',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'ABC123',
            'status' => EventStatus::Active,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(5),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'twk-materi',
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        $firstOptionId = null;

        foreach ([1, 2] as $sortOrder) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => "Soal nomor {$sortOrder}?",
                'explanation' => 'Pembahasan.',
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            $correct = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => 'Jawaban benar',
                'is_correct' => true,
                'sort_order' => 1,
            ]);

            QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'B',
                'content' => 'Jawaban salah',
                'is_correct' => false,
                'sort_order' => 2,
            ]);

            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => $sortOrder,
            ]);

            if ($sortOrder === 1) {
                $firstOptionId = $correct->id;
            }
        }

        return [
            'user' => $user,
            'event' => $event,
            'session' => $session,
            'exam' => $exam,
            'attempt' => $attempt->fresh(),
            'firstOptionId' => $firstOptionId,
        ];
    }
}
