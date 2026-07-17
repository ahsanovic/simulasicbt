<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_time_to_a_single_participant(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $target = $attempts[0];
        $before = $target->expires_at;

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 10)
            ->call('addTime', $target->id)
            ->assertHasNoErrors();

        $after = $target->fresh()->expires_at;
        $this->assertTrue($after->greaterThan($before));
        $this->assertEqualsWithDelta(10, $before->diffInMinutes($after), 0.2);

        $this->assertEqualsWithDelta(
            0,
            $attempts[1]->expires_at->diffInMinutes($attempts[1]->fresh()->expires_at),
            0.2,
        );
    }

    public function test_admin_can_add_time_to_all_selected_participants(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 15)
            ->set('selected', [(string) $attempts[0]->id, (string) $attempts[1]->id])
            ->call('addTimeToSelected')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        foreach ([$attempts[0], $attempts[1]] as $attempt) {
            $this->assertEqualsWithDelta(15, $attempt->expires_at->diffInMinutes($attempt->fresh()->expires_at), 0.2);
        }
    }

    public function test_select_all_only_selects_in_progress_attempts(): void
    {
        [$admin, $event, $session, $attempts, $finished] = $this->createSessionWithAttempts();

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('selectAll', true);

        $selected = $component->get('selected');

        $this->assertContains((string) $attempts[0]->id, $selected);
        $this->assertContains((string) $attempts[1]->id, $selected);
        $this->assertNotContains((string) $finished->id, $selected);
    }

    public function test_livescore_only_shows_its_own_session(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();

        // A second session with its own participant must not appear in the first session's livescore.
        $otherSession = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 2',
            'code' => 'OTHER1',
            'status' => EventStatus::Active,
        ]);
        $otherPeserta = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Sesi Dua']);
        $otherAttempt = ExamAttempt::query()->create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $otherSession->id,
            'user_id' => $otherPeserta->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('rows');

        $attemptIds = array_column($rows, 'attempt_id');
        $this->assertContains($attempts[0]->id, $attemptIds);
        $this->assertNotContains($otherAttempt->id, $attemptIds);
        $this->assertCount(3, $rows); // 2 in-progress + 1 finished from this session only
    }

    public function test_adding_time_revives_a_just_expired_attempt(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $target = $attempts[0];
        $target->update(['expires_at' => now()->subMinutes(3)]);

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 5)
            ->call('addTime', $target->id)
            ->assertHasNoErrors();

        $this->assertTrue($target->fresh()->expires_at->isFuture());
        $this->assertSame(ExamAttemptStatus::InProgress, $target->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Event, 2: EventSession, 3: array<int, ExamAttempt>, 4: ExamAttempt}
     */
    private function createSessionWithAttempts(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Event',
            'slug' => 'simulasi-event',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Offline',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'XYZ789',
            'status' => EventStatus::Active,
        ]);

        $attempts = [];

        foreach (range(0, 1) as $i) {
            $peserta = User::factory()->create(['role' => UserRole::Peserta]);
            $attempts[$i] = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'event_id' => $event->id,
                'event_session_id' => $session->id,
                'user_id' => $peserta->id,
                'started_at' => now()->subMinutes(10),
                'expires_at' => now()->addMinutes(30),
                'status' => ExamAttemptStatus::InProgress,
            ]);
        }

        $finishedPeserta = User::factory()->create(['role' => UserRole::Peserta]);
        $finished = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $finishedPeserta->id,
            'started_at' => now()->subMinutes(40),
            'submitted_at' => now()->subMinutes(5),
            'expires_at' => now()->subMinutes(5),
            'status' => ExamAttemptStatus::Submitted,
            'total_score' => 100,
        ]);

        return [$admin, $event, $session, $attempts, $finished];
    }
}
