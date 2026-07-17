<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_event_pages_and_exports_render(): void
    {
        [$admin, $event, $session] = $this->makeEvent();

        $this->actingAs($admin);

        $this->get(route('admin.events.index'))->assertOk()->assertSee($event->name);
        $this->get(route('admin.events.sessions', $event))->assertOk()->assertSee($session->name)->assertSee($session->code);
        $this->get(route('admin.events.sessions.livescore', [$event, $session]))->assertOk();
        $this->get(route('admin.events.export', $event))->assertOk();
        $this->get(route('admin.events.sessions.export', [$event, $session]))->assertOk();
    }

    public function test_peserta_event_page_lists_active_sessions(): void
    {
        [, $event, $session] = $this->makeEvent();
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('peserta.events.index'))
            ->assertOk()
            ->assertSee($event->name)
            ->assertSee($session->name);
    }

    /**
     * @return array{0: User, 1: Event, 2: EventSession}
     */
    private function makeEvent(): array
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
            'name' => 'Tryout Offline Smoke',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'SMOKE1',
            'status' => EventStatus::Active,
        ]);

        return [$admin, $event, $session];
    }
}
