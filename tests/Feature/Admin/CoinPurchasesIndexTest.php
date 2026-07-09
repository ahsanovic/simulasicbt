<?php

namespace Tests\Feature\Admin;

use App\Enums\HelpItem;
use App\Enums\UserRole;
use App\Livewire\Admin\CoinPurchases\Index;
use App\Models\User;
use App\Services\CoinService;
use App\Services\HelpItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoinPurchasesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_coin_purchases_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.coin-purchases.index'))
            ->assertOk()
            ->assertSee('Pembelian Toko Koin');
    }

    public function test_peserta_cannot_access_coin_purchases_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('admin.coin-purchases.index'))
            ->assertForbidden();
    }

    public function test_coin_purchases_page_lists_help_item_transactions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Budi Santoso',
        ]);

        app(CoinService::class)->award($peserta, 'test_reward', 1, 500, 'Bonus uji coba');
        app(HelpItemService::class)->purchase($peserta, HelpItem::SkipTracker);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Skip Tracker')
            ->assertSee('200');
    }
}
