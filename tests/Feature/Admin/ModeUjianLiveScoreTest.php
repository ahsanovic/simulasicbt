<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\SkbExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModeUjianLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_skd_only_mode_ujian_event_uses_the_unchanged_skd_board_with_no_type_picker(): void
    {
        [$admin, $event, $session] = $this->makeModeUjianEvent(EventExamMode::Skd);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $component->assertViewHas('category', 'skd');
        $component->assertViewHas('showExamTypePicker', false);
        $component->assertDontSee('Jenis Ujian');
    }

    public function test_skb_only_board_shows_jabatan_and_registered_participants_who_have_not_started(): void
    {
        [$admin, $event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        $user = User::factory()->create(['role' => UserRole::Peserta]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Belum Mulai SKB',
            'nik' => '3201010101010001',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $component->assertViewHas('category', 'skb');
        $component->assertViewHas('showExamTypePicker', false);
        $component->assertSee('Belum Mulai SKB');
        $component->assertSee('Belum Mulai');
        $component->assertSee($jabatan->name);

        $rows = $component->instance()->allRows();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['attempt_id']);
        $this->assertSame(0, $rows[0]['benar']);
        $this->assertSame($jabatan->name, $rows[0]['jabatan']);
    }

    public function test_skb_only_board_shows_finished_score_for_submitted_attempt(): void
    {
        [$admin, $event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta SKB Selesai',
            'nik' => '3201010101010002',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        SkbExamAttempt::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'event_participant_id' => $participant->id,
            'user_id' => $user->id,
            'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(40),
            'status' => ExamAttemptStatus::Submitted,
            'correct_score' => 5,
            'correct_count' => 7,
            'total_score' => 35,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $rows = $component->instance()->allRows();
        $this->assertCount(1, $rows);
        $this->assertSame(7, $rows[0]['benar']);
        $this->assertSame(35, $rows[0]['score']);
        $this->assertSame('Selesai', $rows[0]['status_label']);
    }

    public function test_skb_reset_restarts_the_attempt_and_keeps_the_same_questions(): void
    {
        [$admin, $event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta Reset',
            'nik' => '3201010101010003',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $attempt = SkbExamAttempt::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'event_participant_id' => $participant->id,
            'user_id' => $user->id,
            'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(40),
            'status' => ExamAttemptStatus::Submitted,
            'correct_score' => 5,
            'correct_count' => 7,
            'total_score' => 35,
        ]);

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->call('resetAttempt', $attempt->id)
            ->assertHasNoErrors();

        $fresh = $attempt->fresh();
        $this->assertSame(ExamAttemptStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->submitted_at);
        $this->assertNull($fresh->correct_count);
        $this->assertNull($fresh->total_score);
    }

    public function test_both_mode_event_defaults_to_skd_board_and_switches_to_skb_via_dropdown(): void
    {
        [$admin, $event, $session, $jabatan, $exam] = $this->makeModeUjianEvent(EventExamMode::Both);

        $userA = User::factory()->create(['role' => UserRole::Peserta]);
        ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userA->id,
            'started_at' => now()->subMinutes(40), 'submitted_at' => now()->subMinutes(5),
            'expires_at' => now()->subMinutes(5), 'status' => ExamAttemptStatus::Submitted, 'total_score' => 150,
        ]);

        $participantB = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => User::factory()->create(['role' => UserRole::Peserta])->id,
            'name' => 'Peserta SKB', 'nik' => '3201010101010007', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);
        SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participantB->id,
            'user_id' => $participantB->user_id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(10), 'submitted_at' => now(), 'expires_at' => now()->addMinutes(50),
            'status' => ExamAttemptStatus::Submitted, 'correct_score' => 5, 'correct_count' => 8, 'total_score' => 40,
        ]);

        // Default: SKD board, exam-type picker present since this event supports both.
        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        $component->assertViewHas('category', 'skd');
        $component->assertViewHas('showExamTypePicker', true);
        $component->assertSee('TWK');
        $component->assertDontSee('Peserta SKB');

        $skdRows = $component->instance()->allRows();
        $this->assertSame(150, $skdRows[0]['score']);

        // Switch to SKB — pure SKB board, no merged status columns anywhere.
        $component->set('viewMode', 'skb');
        $component->assertViewHas('category', 'skb');
        $component->assertSee('Peserta SKB');
        $component->assertSee('Benar');
        $component->assertDontSee('TWK');

        $skbRows = $component->instance()->allRows();
        $this->assertSame(40, $skbRows[0]['score']);
        $this->assertSame($jabatan->name, $skbRows[0]['jabatan']);
    }

    public function test_status_on_the_right_reflects_the_currently_selected_board_not_a_stale_merged_view(): void
    {
        [$admin, $event, $session, $jabatan, $exam] = $this->makeModeUjianEvent(EventExamMode::Both);

        // SKD finished, SKB still in progress — each board must report its
        // own accurate status, never "Selesai" for the attempt actually running.
        // ExamAttempt::resolvedDisplayName() reads the User's name, not the
        // EventParticipant's — set both so the SKD board's row can be keyed by name.
        $user = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Campuran']);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta Campuran', 'nik' => '3201010101010008', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);
        ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'started_at' => now()->subMinutes(40), 'submitted_at' => now()->subMinutes(5),
            'expires_at' => now()->subMinutes(5), 'status' => ExamAttemptStatus::Submitted, 'total_score' => 100,
        ]);
        SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participant->id,
            'user_id' => $user->id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(5), 'expires_at' => now()->addMinutes(55),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        // SKD board: this attempt is genuinely finished.
        $skdRows = collect($component->instance()->allRows())->keyBy('name');
        $this->assertFalse($skdRows['Peserta Campuran']['in_progress']);

        // SKB board: this attempt is genuinely still running — the row-level
        // in_progress flag (which the "Status" badge renders from) must
        // never read as finished while the attempt is still open.
        $component->set('viewMode', 'skb');
        $skbRows = collect($component->instance()->allRows())->keyBy('name');
        $this->assertTrue($skbRows['Peserta Campuran']['in_progress']);
        $component->assertSee('Masih Ujian');
    }

    /**
     * @return array{0: User, 1: Event, 2: EventSession, 3: JabatanSkb, 4: Exam}
     */
    private function makeModeUjianEvent(EventExamMode $mode): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian',
            'slug' => 'simulasi-mode-ujian-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Livescore',
            'slug' => 'analis-livescore-'.uniqid(),
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'name' => 'Mode Ujian Livescore Test',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => $mode,
            'skb_question_count' => 10,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skd_pin' => $mode->includesSkd() ? '1234' : null,
            'skb_pin' => $mode->includesSkb() ? '5678' : null,
            'status' => EventStatus::Active,
        ]);

        return [$admin, $event, $session, $jabatan, $exam];
    }
}
