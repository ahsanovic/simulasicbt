<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\Users\Index;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormationRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_formation_recap_groups_selected_positions_by_rumpun(): void
    {
        $formationA = Formation::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'group' => 'Hukum & Tata Kelola',
        ]);

        $formationB = Formation::query()->create([
            'name' => 'Pranata Komputer',
            'slug' => 'pranata-komputer',
            'group' => 'Teknologi Informasi',
        ]);

        User::factory()->count(2)->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formationA->id,
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formationB->id,
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => null,
        ]);

        $recap = Formation::query()
            ->whereHas('users', fn ($query) => $query->where('role', UserRole::Peserta))
            ->withCount(['users as peserta_count' => fn ($query) => $query->where('role', UserRole::Peserta)])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        $this->assertSame(2, $recap->get('Hukum & Tata Kelola')->first()->peserta_count);
        $this->assertSame(1, $recap->get('Teknologi Informasi')->first()->peserta_count);
        $this->assertSame(3, User::query()->where('role', UserRole::Peserta)->whereNotNull('formation_id')->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Peserta)->whereNull('formation_id')->count());
    }

    public function test_admin_users_index_filters_by_formation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $formation = Formation::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'group' => 'Hukum & Tata Kelola',
        ]);

        $matched = User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Peserta Terpilih',
            'formation_id' => $formation->id,
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Peserta Tanpa Jabatan',
            'formation_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('formationFilter', (string) $formation->id)
            ->assertSee('Peserta Terpilih')
            ->assertDontSee('Peserta Tanpa Jabatan');
    }

    public function test_admin_users_index_filters_peserta_without_formation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $formation = Formation::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'group' => 'Hukum & Tata Kelola',
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Peserta Terpilih',
            'formation_id' => $formation->id,
        ]);

        $unselected = User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Peserta Tanpa Jabatan',
            'formation_id' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('formationFilter', 'none')
            ->assertSee('Peserta Tanpa Jabatan')
            ->assertDontSee('Peserta Terpilih');
    }
}
