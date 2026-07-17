<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Events\Index as PesertaEvents;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSessionJoinTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_session_code_is_rejected(): void
    {
        [$user, $session] = $this->makeActiveSession();

        Livewire::actingAs($user)
            ->test(PesertaEvents::class)
            ->call('openJoinModal', $session->id)
            ->set('code', 'WRONG9')
            ->call('join')
            ->assertHasErrors('code');

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_cannot_join_a_non_active_session(): void
    {
        [$user, $session] = $this->makeActiveSession();
        $session->update(['status' => EventStatus::Closed]);

        Livewire::actingAs($user)
            ->test(PesertaEvents::class)
            ->call('openJoinModal', $session->id)
            ->set('code', $session->code)
            ->call('join')
            ->assertHasErrors('code');

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_correct_code_resumes_an_existing_active_session_attempt(): void
    {
        [$user, $session, $event] = $this->makeActiveSession();

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        Livewire::actingAs($user)
            ->test(PesertaEvents::class)
            ->call('openJoinModal', $session->id)
            ->set('code', $session->code)
            ->call('join')
            ->assertHasNoErrors()
            ->assertRedirect(route('peserta.exam.room', $event->exam));

        // Resumed, not duplicated.
        $this->assertSame(1, ExamAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame($session->id, $attempt->fresh()->event_session_id);
    }

    public function test_finished_session_blocks_rejoin(): void
    {
        [$user, $session, $event] = $this->makeActiveSession();

        ExamAttempt::query()->create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
            'expires_at' => now(),
            'status' => ExamAttemptStatus::Submitted,
        ]);

        Livewire::actingAs($user)
            ->test(PesertaEvents::class)
            ->call('openJoinModal', $session->id)
            ->set('code', $session->code)
            ->call('join')
            ->assertHasErrors('code');
    }

    /**
     * @return array{0: User, 1: EventSession, 2: Event}
     */
    private function makeActiveSession(): array
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
            'name' => 'Tryout Offline',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'JOIN12',
            'status' => EventStatus::Active,
        ]);

        return [$user, $session, $event];
    }
}
