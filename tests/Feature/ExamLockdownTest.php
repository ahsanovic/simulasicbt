<?php

namespace Tests\Feature;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Auth\ExamLogin;
use App\Livewire\Auth\Login;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\User;
use App\Support\ExamLockdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mode Sedang Ujian: while an official exam runs, the simulasi is closed and
 * only admins and Mode Ujian participants get in, via the exam login.
 */
class ExamLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_open_and_exam_login_redirects_when_inactive(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat datang')
            ->assertDontSee('Sedang Berlangsung Ujian');

        Livewire::test(ExamLogin::class)->assertRedirect(route('login'));
    }

    public function test_simulasi_login_works_normally_when_inactive(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);

        Livewire::test(Login::class)
            ->set('login', $peserta->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('peserta.dashboard'));

        $this->assertAuthenticatedAs($peserta);
    }

    public function test_simulasi_login_rejects_wrong_password(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);

        Livewire::test(Login::class)
            ->set('login', $peserta->username)
            ->set('password', 'salah')
            ->call('authenticate')
            ->assertHasErrors('login');

        $this->assertGuest();
    }

    public function test_login_page_shows_closure_notice_when_active(): void
    {
        ExamLockdown::save(true, '2026-10-02 15:00', 'Ujian Seleksi Sesi 1–3');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sedang Berlangsung Ujian')
            ->assertSee('Ujian CBT BKD Provinsi Jawa Timur')
            ->assertSee('2 Oktober 2026 pukul 15:00')
            ->assertSee('Ujian Seleksi Sesi 1–3')
            ->assertSee(route('ujian.login'))
            ->assertDontSee('Selamat datang')
            ->assertDontSee('Simulasi');
    }

    public function test_google_login_is_closed_when_active(): void
    {
        ExamLockdown::save(true, null, null);

        $this->get(route('auth.google.redirect'))
            ->assertOk()
            ->assertSee('Sedang Berlangsung Ujian')
            ->assertSee('Akan diumumkan kemudian');
    }

    public function test_simulasi_login_form_cannot_be_submitted_when_active(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);
        $component = Livewire::test(Login::class);

        ExamLockdown::save(true, null, null);

        $component->set('login', $peserta->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_exam_login_accepts_mode_ujian_participant(): void
    {
        ExamLockdown::save(true, null, null);
        $participant = $this->modeUjianParticipant();

        Livewire::test(ExamLogin::class)
            ->set('login', $participant->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('peserta.mode-ujian.dashboard'));

        $this->assertAuthenticatedAs($participant);
    }

    public function test_exam_login_accepts_admin(): void
    {
        ExamLockdown::save(true, null, null);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        Livewire::test(ExamLogin::class)
            ->set('login', $admin->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_exam_login_rejects_regular_simulasi_peserta(): void
    {
        ExamLockdown::save(true, null, null);
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);

        Livewire::test(ExamLogin::class)
            ->set('login', $peserta->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('login');

        $this->assertGuest();
    }

    public function test_logged_in_simulasi_peserta_is_blocked_when_active(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);
        ExamLockdown::save(true, null, null);

        $this->actingAs($peserta)
            ->get(route('peserta.simulasi.index'))
            ->assertOk()
            ->assertSee('Sedang Berlangsung Ujian')
            ->assertSee('Keluar');
    }

    public function test_mode_ujian_participant_keeps_access_with_exam_branding(): void
    {
        $participant = $this->modeUjianParticipant();
        ExamLockdown::save(true, null, null);

        $this->actingAs($participant)
            ->get(route('peserta.mode-ujian.dashboard'))
            ->assertOk()
            ->assertDontSee('Sedang Berlangsung Ujian')
            ->assertSee('<title>', false)
            ->assertSee('Ujian CBT BKD Provinsi Jawa Timur');
    }

    public function test_admin_can_toggle_lockdown_from_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('lockdownActive', true)
            ->set('lockdownReopensAt', '2026-10-02T15:00')
            ->set('lockdownMessage', 'Sesi 1')
            ->call('saveLockdown');

        $this->assertTrue(ExamLockdown::active());
        $this->assertSame('2026-10-02 15:00', ExamLockdown::reopensAt()?->format('Y-m-d H:i'));

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertSee('Mode Sedang Ujian aktif');

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->assertSet('lockdownActive', true)
            ->set('lockdownActive', false)
            ->call('saveLockdown');

        $this->assertFalse(ExamLockdown::active());

        auth()->logout();
        $this->get(route('login'))->assertSee('Selamat datang');
    }

    private function modeUjianParticipant(): User
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta, 'is_active' => true]);

        $event = Event::query()->create([
            'name' => 'Ujian Resmi',
            'exam_id' => null,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'status' => EventStatus::Active,
        ]);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta Ujian',
            'nik' => '3201010101017777',
            'jabatan_label' => 'Analis',
        ]);

        return $user;
    }
}
