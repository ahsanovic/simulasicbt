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

    public function test_formation_recap_summarizes_groups_and_top_positions(): void
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

        $formationC = Formation::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'group' => 'Keuangan & Pengawasan',
        ]);

        User::factory()->count(3)->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formationA->id,
        ]);

        User::factory()->count(2)->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formationB->id,
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => $formationC->id,
        ]);

        User::factory()->create([
            'role' => UserRole::Peserta,
            'formation_id' => null,
        ]);

        $formationsWithCount = Formation::query()
            ->whereHas('users', fn ($query) => $query->where('role', UserRole::Peserta))
            ->withCount(['users as peserta_count' => fn ($query) => $query->where('role', UserRole::Peserta)])
            ->orderByDesc('peserta_count')
            ->orderBy('name')
            ->get();

        $groupSummary = $formationsWithCount
            ->groupBy('group')
            ->map(fn ($formations, $group) => [
                'group' => $group,
                'peserta_count' => $formations->sum('peserta_count'),
                'formation_count' => $formations->count(),
            ])
            ->sortByDesc('peserta_count')
            ->values();

        $this->assertSame(3, $groupSummary->first()['peserta_count']);
        $this->assertSame('Hukum & Tata Kelola', $groupSummary->first()['group']);
        $this->assertSame(3, $formationsWithCount->count());
        $this->assertSame('Analis Kebijakan', $formationsWithCount->first()->name);
        $this->assertCount(3, $formationsWithCount->take(5));
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
