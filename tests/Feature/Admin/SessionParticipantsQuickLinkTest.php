<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\Participants;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SessionParticipantsQuickLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_page_links_to_pre_filtered_participants_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::create([
            'name' => 'Quick Link Test', 'code' => Event::generateUniqueCode(),
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd, 'status' => 'active',
        ]);

        $sessionA = EventSession::create(['event_id' => $event->id, 'name' => 'Sesi A', 'code' => EventSession::generateUniqueCode(), 'skd_pin' => '1111', 'status' => 'active']);
        $sessionB = EventSession::create(['event_id' => $event->id, 'name' => 'Sesi B', 'code' => EventSession::generateUniqueCode(), 'skd_pin' => '2222', 'status' => 'active']);

        $userA = User::factory()->create(['role' => UserRole::Peserta]);
        $userB = User::factory()->create(['role' => UserRole::Peserta]);

        EventParticipant::create(['event_id' => $event->id, 'event_session_id' => $sessionA->id, 'user_id' => $userA->id, 'name' => 'Peserta A', 'nik' => '1111111111111111', 'jabatan_label' => 'x']);
        EventParticipant::create(['event_id' => $event->id, 'event_session_id' => $sessionB->id, 'user_id' => $userB->id, 'name' => 'Peserta B', 'nik' => '2222222222222222', 'jabatan_label' => 'x']);

        // Sessions page shows the "Peserta" link and correct per-session count.
        $sessionsResponse = $this->actingAs($admin)->get(route('admin.events.sessions', $event));
        $sessionsResponse->assertOk();
        $sessionsResponse->assertSee('Peserta');
        $expectedUrl = route('admin.events.participants', ['event' => $event, 'sessionFilter' => $sessionA->id]);
        $sessionsResponse->assertSee(e($expectedUrl), false);

        // Visiting that URL pre-filters the participants list to session A only.
        $participantsResponse = $this->actingAs($admin)->get($expectedUrl);
        $participantsResponse->assertOk();
        $participantsResponse->assertSee('Peserta A');
        $participantsResponse->assertDontSee('Peserta B');

        // Also verify via the Livewire component directly with the query param.
        Livewire::actingAs($admin)
            ->withQueryParams(['sessionFilter' => $sessionB->id])
            ->test(Participants::class, ['event' => $event])
            ->assertSet('sessionFilter', $sessionB->id)
            ->assertSee('Peserta B')
            ->assertDontSee('Peserta A');
    }
}
