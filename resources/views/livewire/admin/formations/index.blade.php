<div>
    <x-ui.page-header title="Kelola Jabatan" description="Database target jabatan untuk fitur Simulasi Kelulusan Formasi di portal peserta.">
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Jabatan
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama jabatan atau rumpun..." class="ui-input pl-10">
            </div>
            <div class="w-full sm:w-56">
                <select wire:model.live="groupFilter" class="ui-select">
                    <option value="">Semua rumpun</option>
                    @foreach ($groups as $groupOption)
                        <option value="{{ $groupOption }}">{{ $groupOption }}</option>
                    @endforeach
                </select>
            </div>
        </x-ui.filter-toolbar>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rumpun</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Slug</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($formations as $formation)
                        <tr wire:key="formation-{{ $formation->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $formation->name }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="ui-badge bg-teal-50 text-teal-700">{{ $formation->group }}</span>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $formation->slug }}</td>
                            <td class="px-5 py-4">
                                <span class="ui-badge bg-slate-100 text-slate-700">{{ $formation->users_count }}</span>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <button wire:click="openEditModal({{ $formation->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                                <button
                                    wire:click="delete({{ $formation->id }})"
                                    wire:confirm="Hapus jabatan {{ $formation->name }}?"
                                    @disabled($formation->users_count > 0)
                                    @class([
                                        'ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50',
                                        'cursor-not-allowed opacity-50' => $formation->users_count > 0,
                                    ])
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                Belum ada jabatan. Tambahkan jabatan atau jalankan seeder formasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($formations->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $formations->links() }}</div>
        @endif
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Jabatan' : 'Tambah Jabatan' }}</h2>
                    <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit="save" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama Jabatan</label>
                        <input type="text" wire:model="name" class="ui-input" placeholder="mis. Pranata Komputer">
                        @error('name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Rumpun</label>
                        <input type="text" wire:model="group" class="ui-input" placeholder="mis. Teknologi Informasi" list="formation-group-suggestions">
                        <datalist id="formation-group-suggestions">
                            @foreach ($groups as $groupOption)
                                <option value="{{ $groupOption }}"></option>
                            @endforeach
                        </datalist>
                        @error('group') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-slate-500">Slug URL dibuat otomatis dari nama jabatan.</p>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
