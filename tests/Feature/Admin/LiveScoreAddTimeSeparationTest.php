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

class LiveScoreAddTimeSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_time_on_skd_board_never_touches_the_skb_attempt_with_the_same_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian', 'slug' => 'simulasi-mode-ujian-'.uniqid(),
            'duration_minutes' => 100, 'status' => ExamStatus::Published, 'created_by' => $admin->id,
        ]);

        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        $event = Event::query()->create([
            'name' => 'Both Mode Event', 'exam_id' => $exam->id, 'status' => EventStatus::Active,
            'created_by' => $admin->id, 'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Both,
            'skb_question_count' => 10, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => '1234', 'skb_pin' => '5678', 'status' => EventStatus::Active,
        ]);

        $userSkd = User::factory()->create(['role' => UserRole::Peserta]);
        $userSkb = User::factory()->create(['role' => UserRole::Peserta]);

        $participantSkb = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userSkb->id,
            'name' => 'Peserta SKB', 'nik' => '3201010101010099', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);

        // Created in this order so both attempts land on id=1 in their own
        // tables — the exact condition where a board mix-up would extend
        // the wrong participant's exam.
        $skdAttempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userSkd->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(50), 'status' => ExamAttemptStatus::InProgress,
        ]);

        $skbAttempt = SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participantSkb->id,
            'user_id' => $userSkb->id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(30), 'status' => ExamAttemptStatus::InProgress,
        ]);

        $this->assertSame($skdAttempt->id, $skbAttempt->id, 'Precondition: both attempts must share the same numeric id.');

        $skbExpiresBefore = $skbAttempt->expires_at;

        // Default board is SKD — adding time by this shared id must extend
        // only the SKD attempt, never the SKB attempt with the same id.
        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 5)
            ->call('addTime', $skdAttempt->id)
            ->assertHasNoErrors();

        $skdAttempt->refresh();
        $skbAttempt->refresh();

        $this->assertEqualsWithDelta(50 + 5, now()->diffInMinutes($skdAttempt->expires_at), 0.2, 'SKD attempt should have been extended by 5 minutes.');
        $this->assertTrue($skbAttempt->expires_at->equalTo($skbExpiresBefore), 'SKB attempt must stay untouched while the SKD board is active.');

        $skdExpiresAfterFirstAdd = $skdAttempt->expires_at;

        // Switch to the SKB board — the same numeric id must now resolve to
        // the SKB attempt only, leaving the SKD attempt untouched.
        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('viewMode', 'skb')
            ->set('addMinutes', 5)
            ->call('addTime', $skbAttempt->id)
            ->assertHasNoErrors();

        $skdAttempt->refresh();
        $skbAttempt->refresh();

        $this->assertEqualsWithDelta(30 + 5, now()->diffInMinutes($skbAttempt->expires_at), 0.2, 'SKB attempt should have been extended by 5 minutes.');
        $this->assertTrue($skdAttempt->expires_at->equalTo($skdExpiresAfterFirstAdd), 'SKD attempt must stay untouched while the SKB board is active.');
    }

    public function test_checkbox_selection_is_kept_independent_per_board_when_switching_view_mode(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian', 'slug' => 'simulasi-mode-ujian-'.uniqid(),
            'duration_minutes' => 100, 'status' => ExamStatus::Published, 'created_by' => $admin->id,
        ]);

        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        $event = Event::query()->create([
            'name' => 'Both Mode Event', 'exam_id' => $exam->id, 'status' => EventStatus::Active,
            'created_by' => $admin->id, 'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Both,
            'skb_question_count' => 10, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => '1234', 'skb_pin' => '5678', 'status' => EventStatus::Active,
        ]);

        $userSkd = User::factory()->create(['role' => UserRole::Peserta]);
        $userSkb = User::factory()->create(['role' => UserRole::Peserta]);

        $participantSkb = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userSkb->id,
            'name' => 'Peserta SKB', 'nik' => '3201010101010098', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);

        $skdAttempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userSkd->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(50), 'status' => ExamAttemptStatus::InProgress,
        ]);

        $skbAttempt = SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participantSkb->id,
            'user_id' => $userSkb->id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(30), 'status' => ExamAttemptStatus::InProgress,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('selected.skd', [(string) $skdAttempt->id]);

        // Switching to the SKB board must not wipe the SKD selection made above.
        $component->set('viewMode', 'skb')
            ->assertSet('selected.skd', [(string) $skdAttempt->id])
            ->set('selected.skb', [(string) $skbAttempt->id]);

        // Bulk-adding time on the SKB board only clears the SKB selection —
        // the untouched SKD selection from before must still be there.
        $component->set('addMinutes', 5)
            ->call('addTimeToSelected')
            ->assertHasNoErrors()
            ->assertSet('selected.skb', [])
            ->assertSet('selected.skd', [(string) $skdAttempt->id]);

        $this->assertEqualsWithDelta(30 + 5, now()->diffInMinutes($skbAttempt->fresh()->expires_at), 0.2);
        $this->assertEqualsWithDelta(50, now()->diffInMinutes($skdAttempt->fresh()->expires_at), 0.2, 'SKD attempt must be untouched by the SKB bulk add-time.');
    }
}
