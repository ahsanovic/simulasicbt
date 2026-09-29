<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\JabatanSkb\Index;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JabatanSkbIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_jabatan_skb_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.jabatan-skb.index'))
            ->assertOk()
            ->assertSee('Soal SKB');
    }

    public function test_peserta_cannot_access_jabatan_skb_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('admin.jabatan-skb.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Analis Kebijakan')
            ->set('description', 'Formasi analis kebijakan publik')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_skbs', [
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
        ]);
    }

    public function test_admin_can_update_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openEditModal', $jabatan->id)
            ->set('name', 'Auditor Ahli')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jabatan_skbs', [
            'id' => $jabatan->id,
            'name' => 'Auditor Ahli',
            'slug' => 'auditor-ahli',
        ]);
    }

    public function test_admin_cannot_delete_jabatan_skb_with_questions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Dokter',
            'slug' => 'dokter',
            'is_active' => true,
        ]);

        SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $jabatan->id);

        $this->assertDatabaseHas('jabatan_skbs', ['id' => $jabatan->id]);
    }

    public function test_admin_can_delete_unused_jabatan_skb(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Pustakawan',
            'slug' => 'pustakawan',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $jabatan->id);

        $this->assertSoftDeleted('jabatan_skbs', ['id' => $jabatan->id]);
    }
}
