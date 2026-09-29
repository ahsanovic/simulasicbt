<?php

namespace App\Livewire\Admin\JabatanSkb;

use App\Models\JabatanSkb;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Soal SKB — Jabatan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jabatan_skbs', 'name')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $jabatanSkbId): void
    {
        $jabatan = JabatanSkb::query()->findOrFail($jabatanSkbId);
        $this->editingId = $jabatan->id;
        $this->name = $jabatan->name;
        $this->description = $jabatan->description ?? '';
        $this->is_active = $jabatan->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $validated['is_active'],
            'slug' => $this->uniqueSlug($validated['name'], $this->editingId),
        ];

        if ($this->editingId) {
            JabatanSkb::query()->findOrFail($this->editingId)->update($data);
        } else {
            JabatanSkb::query()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);
        }

        session()->flash('success', 'Jabatan SKB berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $jabatanSkbId): void
    {
        $jabatan = JabatanSkb::query()->withCount('questions')->findOrFail($jabatanSkbId);

        if ($jabatan->questions_count > 0) {
            session()->flash('error', "Jabatan \"{$jabatan->name}\" tidak bisa dihapus karena masih memiliki {$jabatan->questions_count} soal.");

            return;
        }

        $jabatan->delete();

        session()->flash('success', 'Jabatan SKB berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $jabatanSkbs = JabatanSkb::query()
            ->withCount('questions')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.jabatan-skb.index', compact('jabatanSkbs'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->is_active = true;
        $this->resetValidation();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (JabatanSkb::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
