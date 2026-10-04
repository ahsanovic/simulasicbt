<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
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
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\SkbExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The exam room must follow the deadline stored in the database (which an
 * admin can extend from the livescore board) and must stop the peserta the
 * moment that deadline really passes.
 */
class ExamDeadlineEnforcementTest extends TestCase
{
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
            ->call('selectOption', $optionId);

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
            ->call('selectOption', $optionId);

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
            ->call('selectOption', $ctx['firstOptionId']);

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
            ->call('selectOption', $ctx['firstOptionId']);

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

    // ---- helpers ---------------------------------------------------------

    /**
     * @return array{0: User, 1: SkbExamAttempt}
     */
    private function startSkbAttempt(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis SKB Deadline',
            'slug' => 'analis-skb-deadline-'.uniqid(),
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $question = SkbQuestion::query()->create([
                'jabatan_skb_id' => $jabatan->id,
                'content' => "<p>Soal {$i}</p>",
                'difficulty' => 'medium',
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $order => $label) {
                $question->options()->create([
                    'label' => $label,
                    'content_type' => QuestionOptionContentType::Text,
                    'content' => "Opsi {$label}",
                    'is_correct' => $label === 'A',
                    'sort_order' => $order + 1,
                ]);
            }
        }

        $event = Event::query()->create([
            'name' => 'Mode Ujian SKB Deadline Test',
            'exam_id' => null,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skb_pin' => '5678',
            'status' => EventStatus::Active,
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta SKB',
            'nik' => '3201010101018888',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $attempt = app(SkbExamService::class)->startAttempt($event, $participant);

        return [$user, $attempt];
    }

    private function skbAnswerAt(SkbExamAttempt $attempt, int $sortOrder): SkbExamAnswer
    {
        return SkbExamAnswer::query()
            ->where('skb_exam_attempt_id', $attempt->id)
            ->where('sort_order', $sortOrder)
            ->with('question.options')
            ->firstOrFail();
    }

    /**
     * @return array{user: User, exam: Exam, attempt: ExamAttempt, firstOptionId: int}
     */
    private function createSkdAttempt(int $expiresInMinutes, bool $modeUjian = false, SubjectCode $subject = SubjectCode::Twk): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Deadline',
            'slug' => 'simulasi-deadline',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Deadline Test',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => $modeUjian,
            'exam_mode' => EventExamMode::Skd,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'DLN123',
            'status' => EventStatus::Active,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'display_name' => 'Peserta Deadline',
            'started_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes($expiresInMinutes)->startOfSecond(),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $subjectCode = $subject;
        $subject = Subject::query()->create([
            'code' => $subjectCode,
            'name' => strtoupper($subjectCode->value),
            'slug' => $subjectCode->value,
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => $subjectCode->value.'-materi',
            'name' => 'Materi '.strtoupper($subjectCode->value),
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
            'exam' => $exam,
            'attempt' => $attempt,
            'firstOptionId' => $firstOptionId,
        ];
    }
}
