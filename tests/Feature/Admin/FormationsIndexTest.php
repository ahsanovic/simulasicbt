<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\Formations\Index;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormationsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_formations_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.formations.index'))
            ->assertOk()
            ->assertSee('Kelola Jabatan');
    }

    public function test_peserta_cannot_access_formations_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('admin.formations.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_formation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openCreateModal')
            ->set('name', 'Analis Kebijakan')
            ->set('group', 'Hukum & Tata Kelola')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('formations', [
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'group' => 'Hukum & Tata Kelola',
        ]);
    }

    public function test_admin_can_update_formation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $formation = Formation::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'group' => 'Keuangan & Pengawasan',
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('openEditModal', $formation->id)
            ->set('name', 'Auditor Ahli')
            ->set('group', 'Keuangan & Pengawasan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('formations', [
            'id' => $formation->id,
            'name' => 'Auditor Ahli',
            'slug' => 'auditor-ahli',
        ]);
    }

    public function test_admin_cannot_delete_formation_used_by_peserta(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $formation = Formation::query()->create([
            'name' => 'Dokter',
            'slug' => 'dokter',
            'group' => 'Kesehatan',
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formation->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $formation->id);

        $this->assertDatabaseHas('formations', ['id' => $formation->id]);
    }

    public function test_admin_can_delete_unused_formation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $formation = Formation::query()->create([
            'name' => 'Pustakawan',
            'slug' => 'pustakawan',
            'group' => 'Pendidikan',
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('delete', $formation->id);

        $this->assertDatabaseMissing('formations', ['id' => $formation->id]);
    }
}
