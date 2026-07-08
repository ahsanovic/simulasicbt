<?php

namespace Tests\Feature\Peserta;

use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\MateriBelajar;
use App\Livewire\Peserta\MateriBelajarShow;
use App\Models\Material;
use App\Models\MaterialCheatSheet;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MateriBelajarTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_materi_belajar_index(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $subject = $this->createSubject(SubjectCode::Twk, 'Tes Wawasan Kebangsaan');
        $material = $this->createMaterial($subject, 'integritas', 'Integritas');
        $this->createPublishedCheatSheet($material, "## Konsep Inti\n\nKonten integritas.");

        $this->actingAs($user)
            ->get(route('peserta.materi.index'))
            ->assertOk()
            ->assertSee('Materi Belajar CPNS')
            ->assertSee('Integritas')
            ->assertSee('1 materi tersedia');
    }

    public function test_peserta_can_view_published_cheat_sheet(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $subject = $this->createSubject(SubjectCode::Twk, 'Tes Wawasan Kebangsaan');
        $material = $this->createMaterial($subject, 'integritas', 'Integritas');
        $this->createPublishedCheatSheet($material, "## Konsep Inti\n\n**Integritas** adalah kunci.");

        $this->actingAs($user)
            ->get(route('peserta.materi.show', [
                'subjectCode' => 'twk',
                'materialSlug' => 'integritas',
            ]))
            ->assertOk()
            ->assertSee('Integritas')
            ->assertSee('Konsep Inti');
    }

    public function test_unpublished_cheat_sheet_returns_not_found(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $subject = $this->createSubject(SubjectCode::Twk, 'Tes Wawasan Kebangsaan');
        $material = $this->createMaterial($subject, 'integritas', 'Integritas');

        MaterialCheatSheet::query()->create([
            'material_id' => $material->id,
            'status' => MaterialCheatSheet::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('peserta.materi.show', [
                'subjectCode' => 'twk',
                'materialSlug' => 'integritas',
            ]))
            ->assertNotFound();
    }

    public function test_index_can_switch_subject_tab(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $twk = $this->createSubject(SubjectCode::Twk, 'Tes Wawasan Kebangsaan');
        $tkp = $this->createSubject(SubjectCode::Tkp, 'Tes Karakteristik Pribadi');
        $this->createMaterial($twk, 'integritas', 'Integritas');
        $this->createMaterial($tkp, 'profesionalisme', 'Profesionalisme');

        Livewire::actingAs($user)
            ->test(MateriBelajar::class)
            ->call('setSubject', 'tkp')
            ->assertSet('activeSubjectCode', 'tkp')
            ->assertSee('Profesionalisme');
    }

    public function test_show_component_mounts_material(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $subject = $this->createSubject(SubjectCode::Twk, 'Tes Wawasan Kebangsaan');
        $material = $this->createMaterial($subject, 'integritas', 'Integritas');
        $this->createPublishedCheatSheet($material, '## Konsep Inti');

        Livewire::actingAs($user)
            ->test(MateriBelajarShow::class, [
                'subjectCode' => 'twk',
                'materialSlug' => 'integritas',
            ])
            ->assertSet('material.id', $material->id)
            ->assertSee('Konsep Inti');
    }

    private function createSubject(SubjectCode $code, string $name): Subject
    {
        return Subject::query()->create([
            'code' => $code,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sort_order' => 1,
        ]);
    }

    private function createMaterial(Subject $subject, string $slug, string $name): Material
    {
        return Material::query()->create([
            'subject_id' => $subject->id,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => 1,
        ]);
    }

    private function createPublishedCheatSheet(Material $material, string $content): MaterialCheatSheet
    {
        return MaterialCheatSheet::query()->create([
            'material_id' => $material->id,
            'content' => $content,
            'status' => MaterialCheatSheet::STATUS_COMPLETED,
            'generated_at' => now(),
        ]);
    }
}
