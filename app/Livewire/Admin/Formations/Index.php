<?php

namespace App\Livewire\Admin\Formations;

use App\Models\Formation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Kelola Jabatan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $groupFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $group = '';

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('formations', 'name')->ignore($this->editingId),
            ],
            'group' => ['required', 'string', 'max:255'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGroupFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'groupFilter']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $formationId): void
    {
        $formation = Formation::query()->findOrFail($formationId);
        $this->editingId = $formation->id;
        $this->name = $formation->name;
        $this->group = $formation->group;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'group' => $validated['group'],
            'slug' => $this->uniqueSlug($validated['name'], $this->editingId),
        ];

        if ($this->editingId) {
            Formation::query()->findOrFail($this->editingId)->update($data);
            $this->forgetFormationCache($this->editingId);
        } else {
            $formation = Formation::query()->create($data);
            $this->forgetFormationCache($formation->id);
        }

        session()->flash('success', 'Jabatan berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $formationId): void
    {
        $formation = Formation::query()->withCount('users')->findOrFail($formationId);

        if ($formation->users_count > 0) {
            session()->flash('error', "Jabatan \"{$formation->name}\" tidak bisa dihapus karena masih dipilih {$formation->users_count} peserta.");

            return;
        }

        $formation->delete();
        $this->forgetFormationCache($formationId);

        session()->flash('success', 'Jabatan berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $formations = Formation::query()
            ->withCount('users')
            ->when($this->search, function ($query) {
                $query->where(function ($builder) {
                    $builder
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('group', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->groupFilter !== '', fn ($query) => $query->where('group', $this->groupFilter))
            ->orderBy('group')
            ->orderBy('name')
            ->paginate(15);

        $groups = Formation::query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return view('livewire.admin.formations.index', compact('formations', 'groups'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'group']);
        $this->resetValidation();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Formation::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function forgetFormationCache(int $formationId): void
    {
        Cache::forget("formation_matchmaking_stats_{$formationId}");
    }
}
