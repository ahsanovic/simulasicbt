<?php

namespace Tests\Feature\Peserta;

use App\Enums\UserRole;
use App\Livewire\Peserta\ProfileModal;
use App\Models\User;
use App\Services\LeaderboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LeaderboardSummaryService::class, function ($mock): void {
            $mock->shouldReceive('getRanks')->andReturn([
                'score' => null,
                'duel' => null,
                'xp' => null,
            ]);
        });
    }

    public function test_dashboard_shows_profil_button_in_user_menu(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Profil');
    }

    public function test_google_user_can_open_profile_modal_and_update_name(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('Nama Google')
            ->assertSee('Ganti Nama Tampilan')
            ->set('name', 'Nama Baru')
            ->call('updateName')
            ->assertHasNoErrors()
            ->assertDispatched('profile-name-updated', name: 'Nama Baru');

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
    }

    public function test_non_google_user_cannot_update_name(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => null,
            'name' => 'Peserta Biasa',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->call('openModal')
            ->assertDontSee('Ganti Nama Tampilan')
            ->set('name', 'Nama Baru')
            ->call('updateName');

        $user->refresh();

        $this->assertSame('Peserta Biasa', $user->name);
    }

    public function test_pegawai_user_cannot_update_name_even_with_google_id(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'is_pegawai' => true,
            'google_id' => 'google-pegawai',
            'name' => 'Pegawai Satu',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->call('openModal')
            ->assertDontSee('Ganti Nama Tampilan')
            ->set('name', 'Nama Baru')
            ->call('updateName');

        $user->refresh();

        $this->assertSame('Pegawai Satu', $user->name);
    }

    public function test_update_name_requires_minimum_length(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->set('name', 'A')
            ->call('updateName')
            ->assertHasErrors(['name']);
    }

    public function test_update_name_rejects_xss_payload(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->set('name', '<script>alert(1)</script>Budi')
            ->call('updateName')
            ->assertHasErrors(['name']);

        $user->refresh();

        $this->assertSame('Nama Google', $user->name);
    }

    public function test_update_name_rejects_urls(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->set('name', 'Budi https://evil.com')
            ->call('updateName')
            ->assertHasErrors(['name']);

        $user->refresh();

        $this->assertSame('Nama Google', $user->name);
    }

    public function test_update_name_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        RateLimiter::clear('profile-name-update:'.$user->id);

        $component = Livewire::actingAs($user)->test(ProfileModal::class);

        $names = ['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component
                ->set('name', 'Nama Baru '.$names[$attempt])
                ->call('updateName')
                ->assertHasNoErrors();
        }

        $component
            ->set('name', 'Nama Baru Enam')
            ->call('updateName')
            ->assertHasErrors(['name']);
    }

    public function test_update_name_strips_html_before_saving(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'google_id' => 'google-123',
            'name' => 'Nama Google',
        ]);

        RateLimiter::clear('profile-name-update:'.$user->id);

        Livewire::actingAs($user)
            ->test(ProfileModal::class)
            ->set('name', 'Budi <b>Santoso</b>')
            ->call('updateName')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Budi Santoso', $user->name);
    }
}
