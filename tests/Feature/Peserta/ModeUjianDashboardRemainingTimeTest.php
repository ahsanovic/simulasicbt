<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\ModeUjian\Dashboard;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModeUjianDashboardRemainingTimeTest extends TestCase
{
    use RefreshDatabase;

    private function makeParticipant(): array
    {
        $exam = Exam::create(['title' => 'Remaining Time Test', 'slug' => 'remaining-time-test', 'duration_minutes' => 100, 'status' => 'published']);

        $event = Event::create([
            'name' => 'Remaining Time Event', 'code' => Event::generateUniqueCode(), 'exam_id' => $exam->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd, 'status' => 'active',
        ]);

        $session = EventSession::create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => EventSession::generateUniquePin('skd_pin'), 'status' => 'active',
        ]);

        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $participant = EventParticipant::create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta Test', 'nik' => '1231231231231234', 'jabatan_label' => 'x',
        ]);

        return compact('exam', 'event', 'session', 'user', 'participant');
    }

    public function test_dashboard_shows_static_duration_when_attempt_not_started(): void
    {
        ['user' => $user, 'exam' => $exam] = $this->makeParticipant();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee("Durasi {$exam->duration_minutes} menit")
            ->assertDontSee('Sisa waktu');
    }

    public function test_dashboard_shows_live_remaining_time_when_attempt_in_progress(): void
    {
        ['user' => $user, 'event' => $event] = $this->makeParticipant();

        ExamAttempt::create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $event->sessions()->first()->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(90),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Sisa waktu')
            ->assertDontSee("Durasi {$event->exam->duration_minutes} menit");
    }
}
