<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamLoadTestUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_participants_in_the_session_and_cleans_them_up(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $real = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'peserta_asli']);
        // "_" is a wildcard in SQL LIKE: this real account must survive the cleanup.
        $lookalike = User::factory()->create(['role' => UserRole::Peserta, 'username' => 'loadtester1']);
        $event = Event::query()->create(['name' => 'Uji Beban', 'status' => EventStatus::Active, 'created_by' => $admin->id]);
        $session = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi 1', 'code' => 'LOAD01', 'status' => EventStatus::Active]);

        $this->artisan('exam:loadtest-users', ['session' => $session->id, '--count' => 3, '--password' => 'rahasia'])->assertSuccessful();

        $user = User::query()->where('username', 'loadtest_002')->firstOrFail();
        $this->assertTrue(Hash::check('rahasia', $user->password));
        $this->assertSame(UserRole::Peserta, $user->role);
        $this->assertSame(3, EventParticipant::query()->where('event_session_id', $session->id)->count());

        // Running it again does not duplicate anything.
        $this->artisan('exam:loadtest-users', ['session' => $session->id, '--count' => 3])->assertSuccessful();
        $this->assertSame(3, User::query()->whereIn('username', $created = ['loadtest_001', 'loadtest_002', 'loadtest_003'])->count());

        $this->artisan('exam:loadtest-users', ['--cleanup' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->whereIn('username', $created)->count());
        $this->assertSame(0, EventParticipant::query()->where('event_session_id', $session->id)->count());
        $this->assertNotNull($real->fresh(), 'Real participants are never touched.');
        $this->assertNotNull($lookalike->fresh(), 'A real "loadtester1" account is not a loadtest_ account.');
    }

    public function test_unknown_session_fails(): void
    {
        $this->artisan('exam:loadtest-users', ['session' => 999])->assertFailed();
    }
}
