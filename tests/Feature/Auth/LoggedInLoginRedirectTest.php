<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A logged-in user opening a login page goes to their own dashboard instead
 * of bouncing between "/" and /login forever (ERR_TOO_MANY_REDIRECTS).
 */
class LoggedInLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_peserta_opening_exam_login_goes_to_dashboard(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)->get(route('ujian.login'))->assertRedirect(route('peserta.dashboard'));
        $this->actingAs($peserta)->get(route('login'))->assertRedirect(route('peserta.dashboard'));
    }

    public function test_logged_in_admin_opening_exam_login_goes_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('ujian.login'))->assertRedirect(route('admin.dashboard'));
    }
}
