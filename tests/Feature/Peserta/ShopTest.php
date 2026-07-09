<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\Shop;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_shop_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.shop.index'))
            ->assertOk()
            ->assertSee('Toko Koin')
            ->assertSee('Skip Tracker')
            ->assertSee('50:50 Eliminator');
    }

    public function test_peserta_can_purchase_help_item_from_shop(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        app(CoinService::class)->award($user, 'test_reward', 1, 500, 'Bonus uji coba');

        Livewire::actingAs($user)
            ->test(Shop::class)
            ->call('purchase', 'skip_tracker')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_help_items', [
            'user_id' => $user->id,
            'item' => 'skip_tracker',
            'quantity' => 1,
        ]);
    }

    public function test_shop_shows_resume_banner_when_simulation_is_in_progress(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Harian',
            'slug' => 'simulasi-harian',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $this->actingAs($user)
            ->get(route('peserta.shop.index'))
            ->assertOk()
            ->assertSee('Simulasi Sedang Berlangsung')
            ->assertSee('Kembali ke Simulasi');
    }
}
