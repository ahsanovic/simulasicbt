<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Livewire\Admin\Events\Participants;
use App\Models\CoinTransaction;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\SkbExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventParticipantDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_participant_also_removes_their_skd_attempt_and_its_rewards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian', 'slug' => 'simulasi-mode-ujian-'.uniqid(),
            'duration_minutes' => 100, 'status' => ExamStatus::Published, 'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Delete Cleanup Event', 'exam_id' => $exam->id, 'status' => EventStatus::Active,
            'created_by' => $admin->id, 'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => '1234', 'status' => EventStatus::Active,
        ]);

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta Dihapus', 'nik' => '3201010101019999', 'jabatan_label' => 'x',
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'started_at' => now()->subMinutes(10), 'submitted_at' => now(), 'expires_at' => now()->addMinutes(90),
            'status' => ExamAttemptStatus::Submitted, 'total_score' => 300,
        ]);

        XpReward::query()->create([
            'user_id' => $user->id, 'amount' => 50, 'source_type' => ExamAttempt::class, 'source_id' => $attempt->id,
        ]);
        CoinTransaction::query()->create([
            'user_id' => $user->id, 'amount' => 10, 'reason' => 'exam_completion', 'source_type' => ExamAttempt::class, 'source_id' => $attempt->id,
        ]);

        // Sanity check: before deletion, the attempt shows up on the SKD livescore board.
        $rowsBefore = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->instance()->allRows();
        $this->assertCount(1, $rowsBefore);

        Livewire::actingAs($admin)
            ->test(Participants::class, ['event' => $event])
            ->call('delete', $participant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('event_participants', ['id' => $participant->id]);
        $this->assertDatabaseMissing('exam_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('xp_rewards', ['source_type' => ExamAttempt::class, 'source_id' => $attempt->id]);
        $this->assertDatabaseMissing('coin_transactions', ['source_type' => ExamAttempt::class, 'source_id' => $attempt->id]);

        // The ghost row must be gone from the livescore board too.
        $rowsAfter = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->instance()->allRows();
        $this->assertCount(0, $rowsAfter);
    }

    public function test_deleting_a_participant_cascades_their_skb_attempt_via_the_database_foreign_key(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian', 'slug' => 'simulasi-mode-ujian-'.uniqid(),
            'duration_minutes' => 100, 'status' => ExamStatus::Published, 'created_by' => $admin->id,
        ]);

        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        $event = Event::query()->create([
            'name' => 'Delete Cleanup SKB Event', 'exam_id' => $exam->id, 'status' => EventStatus::Active,
            'created_by' => $admin->id, 'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 10, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skb_pin' => '5678', 'status' => EventStatus::Active,
        ]);

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta SKB Dihapus', 'nik' => '3201010101018888', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);

        $skbAttempt = SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participant->id,
            'user_id' => $user->id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(60), 'status' => ExamAttemptStatus::InProgress,
        ]);

        Livewire::actingAs($admin)
            ->test(Participants::class, ['event' => $event])
            ->call('delete', $participant->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('event_participants', ['id' => $participant->id]);
        $this->assertDatabaseMissing('skb_exam_attempts', ['id' => $skbAttempt->id]);
    }
}
