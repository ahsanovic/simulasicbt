<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_admin_can_authenticate_via_livewire(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(Login::class)
            ->set('login', $user->username)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_peserta_can_authenticate_via_livewire(): void
    {
        $user = User::factory()->pegawai()->create(['role' => UserRole::Peserta]);

        Livewire::test(Login::class)
            ->set('login', $user->nip)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('peserta.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->pegawai()->create();

        Livewire::test(Login::class)
            ->set('login', $user->nip)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors('login');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
