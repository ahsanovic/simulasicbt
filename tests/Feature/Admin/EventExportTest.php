<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Exports\EventParticipantsExport;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_export_includes_all_sessions_with_session_column(): void
    {
        [$event, $sessionOne, $sessionTwo] = $this->makeEventWithTwoSessions();

        $rows = (new EventParticipantsExport($event))->collection();

        // One participant per session = 2 rows total.
        $this->assertCount(2, $rows);

        $sessionNames = $rows->map(fn ($row) => $row[4])->all();
        $this->assertContains($sessionOne->name, $sessionNames);
        $this->assertContains($sessionTwo->name, $sessionNames);
    }

    public function test_session_export_is_scoped_to_that_session(): void
    {
        [$event, $sessionOne] = $this->makeEventWithTwoSessions();

        $rows = (new EventParticipantsExport($event, $sessionOne))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame($sessionOne->name, $rows->first()[4]);
    }

    public function test_export_headings_contain_session_and_score_columns(): void
    {
        [$event] = $this->makeEventWithTwoSessions();

        $headings = (new EventParticipantsExport($event))->headings();

        $this->assertContains('Sesi', $headings);
        $this->assertContains('Total Skor', $headings);
        $this->assertContains('Instansi', $headings);
    }

    /**
     * @return array{0: Event, 1: EventSession, 2: EventSession}
     */
    private function makeEventWithTwoSessions(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instansi = Instansi::query()->create(['id' => 900, 'nama' => 'Dinas Uji']);

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

        $sessions = [];
        foreach (['Sesi 1', 'Sesi 2'] as $i => $name) {
            $session = EventSession::query()->create([
                'event_id' => $event->id,
                'name' => $name,
                'code' => 'CODE'.$i,
                'status' => EventStatus::Active,
            ]);

            $peserta = User::factory()->create([
                'role' => UserRole::Peserta,
                'instansi_id' => $instansi->id,
            ]);

            ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'event_id' => $event->id,
                'event_session_id' => $session->id,
                'user_id' => $peserta->id,
                'started_at' => now()->subMinutes(20),
                'submitted_at' => now(),
                'expires_at' => now()->addMinutes(80),
                'status' => ExamAttemptStatus::Submitted,
                'score_twk' => 50,
                'score_tiu' => 60,
                'score_tkp' => 40,
                'total_score' => 150,
            ]);

            $sessions[] = $session;
        }

        return [$event, $sessions[0], $sessions[1]];
    }
}
