<?php

namespace Tests\Feature\Auth;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Livewire\Auth\ExamLogin;
use App\Livewire\Auth\Login;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\User;
use App\Support\ExamLockdown;
use App\Support\ModeUjianPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Login looks accounts up with indexed queries only, Mode Ujian participant
 * accounts (password = NIK) use the cheaper bcrypt cost on exam days, and
 * every other account keeps the application default.
 */
class ModeUjianLoginCostTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '3201010101010001';

    private ?Event $event = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Production cost (tests run with BCRYPT_ROUNDS=4).
        config(['hashing.bcrypt.rounds' => 12]);
        $this->app['hash']->forgetDrivers();
    }

    public function test_peserta_logs_in_with_username_nip_or_nik(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta, 'is_active' => true, 'username' => 'budi',
            'nip' => '198001012010011001', 'nik' => self::NIK, 'password' => Hash::make('rahasia123'),
        ]);
        User::factory()->create(['role' => UserRole::Peserta, 'is_active' => false, 'nip' => '198001012010011002', 'password' => Hash::make('rahasia123')]);

        foreach (['budi', '198001012010011001', self::NIK] as $login) {
            Livewire::test(Login::class)->set('login', $login)->set('password', 'rahasia123')->call('authenticate')->assertHasNoErrors();
            $this->assertAuthenticatedAs($user);
            Auth::logout();
        }

        Livewire::test(Login::class)->set('login', '198001012010011002')->set('password', 'rahasia123')->call('authenticate')->assertHasErrors('login');
        $this->assertGuest();
    }

    public function test_exam_login_lightens_the_participant_hash_and_a_normal_login_restores_it(): void
    {
        $user = $this->participant();
        $this->assertSame(12, $this->cost($user));

        ExamLockdown::save(true, null, null);
        Livewire::test(ExamLogin::class)->set('login', self::NIK)->set('password', self::NIK)->call('authenticate')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(ModeUjianPassword::ROUNDS, $this->cost($user->fresh()));

        Auth::logout();
        ExamLockdown::save(false, null, null);
        Livewire::test(Login::class)->set('login', self::NIK)->set('password', self::NIK)->call('authenticate')->assertHasNoErrors();
        $this->assertSame(12, $this->cost($user->fresh()));
    }

    public function test_admin_keeps_the_default_cost_on_the_exam_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true, 'username' => 'admin1', 'password' => Hash::make('rahasia123')]);
        ExamLockdown::save(true, null, null);

        Livewire::test(ExamLogin::class)->set('login', 'admin1')->set('password', 'rahasia123')->call('authenticate')->assertHasNoErrors();

        $this->assertSame(12, $this->cost($admin->fresh()));
    }

    public function test_prepare_logins_converts_only_accounts_still_on_their_nik(): void
    {
        $onNik = $this->participant();
        $changed = $this->participant(nik: '3201010101010002', password: 'gantipassword');
        $ready = $this->participant(nik: '3201010101010003');
        $ready->forceFill(['password' => ModeUjianPassword::hash('3201010101010003')])->saveQuietly();
        $changedHash = $changed->password;

        $this->artisan('exam:prepare-logins', ['event' => $this->event->id])
            ->expectsOutputToContain('1 diringankan, 1 sudah ringan, 1 dilewati')
            ->assertSuccessful();

        $this->assertSame(ModeUjianPassword::ROUNDS, $this->cost($onNik->fresh()));
        $this->assertTrue(Hash::check(self::NIK, $onNik->fresh()->password));
        $this->assertSame($changedHash, $changed->fresh()->password, 'A changed password is left alone.');
    }

    public function test_the_mode_ujian_cost_never_exceeds_the_configured_cost(): void
    {
        config(['hashing.bcrypt.rounds' => 4]);
        $this->app['hash']->forgetDrivers();

        $user = User::factory()->create(['password' => ModeUjianPassword::hash(self::NIK)]);

        $this->assertSame(4, $this->cost($user));
    }

    private function participant(string $nik = self::NIK, ?string $password = null): User
    {
        $event = $this->event ??= Event::query()->create([
            'name' => 'Ujian Resmi', 'exam_id' => null, 'status' => EventStatus::Active,
            'created_by' => User::factory()->create(['role' => UserRole::Admin])->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);
        $session = EventSession::query()->firstOrCreate(['event_id' => $event->id], [
            'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(), 'status' => EventStatus::Active,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Peserta, 'is_active' => true, 'nik' => $nik, 'password' => Hash::make($password ?? $nik),
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta', 'nik' => $nik, 'jabatan_label' => '-',
        ]);

        return $user;
    }

    private function cost(User $user): int
    {
        return password_get_info($user->password)['options']['cost'];
    }
}
