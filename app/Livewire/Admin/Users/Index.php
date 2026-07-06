<?php

namespace App\Livewire\Admin\Users;

use App\Enums\UserRole;
use App\Livewire\Concerns\HandlesImportErrorModal;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Pengguna')]
class Index extends Component
{
    use HandlesImportErrorModal, WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $username = '';

    public string $nip = '';

    public ?int $instansi_id = null;

    public string $instansiSearch = '';

    public bool $is_pegawai = false;

    public string $password = '';

    public string $role = 'peserta';

    public bool $is_active = true;

    public bool $showImportModal = false;

    public function mount(): void
    {
        $this->mountImportErrorModal();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($this->editingId)],
            'nip' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'nip')->ignore($this->editingId),
                Rule::requiredIf(fn () => $this->is_pegawai),
            ],
            'instansi_id' => [
                'nullable',
                'integer',
                'exists:instansis,id',
                Rule::requiredIf(fn () => $this->is_pegawai),
            ],
            'is_pegawai' => ['boolean'],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'peserta'])],
            'is_active' => ['boolean'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedIsPegawai(): void
    {
        if (! $this->is_pegawai) {
            $this->nip = '';
            $this->instansi_id = null;
            $this->instansiSearch = '';
        }
    }

    public function updatedInstansiSearch(): void
    {
        if ($this->instansiSearch === '') {
            $this->instansi_id = null;

            return;
        }

        if ($this->instansi_id !== null) {
            $nama = Instansi::query()->whereKey($this->instansi_id)->value('nama');

            if ($nama !== $this->instansiSearch) {
                $this->instansi_id = null;
            }
        }
    }

    public function selectInstansi(int $id): void
    {
        $instansi = Instansi::query()->find($id);

        if ($instansi === null) {
            return;
        }

        $this->instansi_id = $instansi->id;
        $this->instansiSearch = $instansi->nama;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->username = $user->username ?? '';
        $this->nip = $user->nip ?? '';
        $this->instansi_id = $user->instansi_id;
        $this->instansiSearch = $user->instansi?->nama ?? '';
        $this->is_pegawai = $user->is_pegawai;
        $this->role = $user->role->value;
        $this->is_active = $user->is_active;
        $this->password = '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'] ?: null,
            'nip' => $validated['is_pegawai'] ? $validated['nip'] : null,
            'instansi_id' => $validated['is_pegawai'] ? $validated['instansi_id'] : null,
            'is_pegawai' => $validated['is_pegawai'] && $validated['role'] === 'peserta',
            'role' => UserRole::from($validated['role']),
            'is_active' => $validated['is_active'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($this->editingId) {
            User::query()->whereKey($this->editingId)->update($data);
            session()->flash('success', 'Pengguna berhasil diperbarui.');
        } else {
            User::query()->create($data);
            session()->flash('success', 'Pengguna berhasil ditambahkan.');
        }

        $this->closeModal();
    }

    public function delete(int $userId): void
    {
        if ($userId === auth()->id()) {
            session()->flash('error', 'Tidak dapat menghapus akun sendiri.');

            return;
        }

        User::query()->whereKey($userId)->delete();
        session()->flash('success', 'Pengguna berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'username', 'nip', 'instansi_id', 'instansiSearch', 'password']);
        $this->role = 'peserta';
        $this->is_pegawai = false;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $users = User::query()
            ->with('instansi')
            ->withSum('audioLearningSessions as total_xp', 'xp_earned')
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('username', 'like', "%{$this->search}%")
                    ->orWhere('nip', 'like', "%{$this->search}%");
            }))
            ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
            ->latest()
            ->paginate(10);

        $instansiSuggestions = collect();

        if ($this->showModal && $this->is_pegawai && $this->instansiSearch !== '') {
            $instansiSuggestions = Instansi::query()
                ->where('nama', 'like', '%'.$this->instansiSearch.'%')
                ->orderBy('nama')
                ->limit(15)
                ->get();
        }

        return view('livewire.admin.users.index', compact('users', 'instansiSuggestions'));
    }
}
