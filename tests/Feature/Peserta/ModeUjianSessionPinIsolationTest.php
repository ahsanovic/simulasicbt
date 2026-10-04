<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ModeUjian\Dashboard;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModeUjianSessionPinIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_cannot_use_another_sessions_pin(): void
    {
        $exam = Exam::create(['title' => 'Isolation Test', 'slug' => 'isolation-test', 'duration_minutes' => 100, 'status' => 'published']);

        $event = Event::create([
            'name' => 'Pin Isolation Event', 'code' => Event::generateUniqueCode(), 'exam_id' => $exam->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd, 'status' => 'active',
        ]);

        $session1 = EventSession::create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => EventSession::generateUniquePin('skd_pin'), 'status' => 'active',
        ]);

        $session2 = EventSession::create([
            'event_id' => $event->id, 'name' => 'Sesi 2', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => EventSession::generateUniquePin('skd_pin'), 'status' => 'active',
        ]);

        $this->assertNotSame($session1->skd_pin, $session2->skd_pin);

        $userX = User::factory()->create(['role' => UserRole::Peserta]);
        EventParticipant::create([
            'event_id' => $event->id, 'event_session_id' => $session1->id, 'user_id' => $userX->id,
            'name' => 'X Peserta', 'nik' => '1231231231231234', 'jabatan_label' => 'x',
        ]);

        // X is registered to session 1 — using session 2's PIN must be rejected.
        Livewire::actingAs($userX)
            ->test(Dashboard::class)
            ->call('openPinModal', 'skd')
            ->set('pinInput', $session2->skd_pin)
            ->call('submitPin')
            ->assertSet('pinError', 'PIN sesi salah.');

        $this->assertDatabaseMissing('exam_attempts', ['user_id' => $userX->id]);

        // X's own session 1 PIN must pass the PIN check. Starting then fails
        // for the unrelated reason that this test seeds no question bank, and
        // that error is shown to the peserta — so the PIN was accepted as long
        // as the message is not the wrong-PIN one.
        $pinError = Livewire::actingAs($userX)
            ->test(Dashboard::class)
            ->call('openPinModal', 'skd')
            ->set('pinInput', $session1->skd_pin)
            ->call('submitPin')
            ->get('pinError');

        $this->assertNotSame('PIN sesi salah.', $pinError);
        $this->assertSame('Bank soal tidak cukup untuk memulai ujian. Hubungi admin.', $pinError);
    }
}
