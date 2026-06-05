@if ($showModal)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h2>
                <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="save" class="space-y-4 p-6">
                <div>
                    <label class="ui-label">Nama Lengkap</label>
                    <input type="text" wire:model="name" class="ui-input">
                    @error('name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ui-label">Email</label>
                    <input type="email" wire:model="email" class="ui-input">
                    @error('email') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ui-label">Username</label>
                    <input type="text" wire:model="username" class="ui-input">
                    @error('username') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                @if ($role === 'peserta')
                    <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-700">
                        <input type="checkbox" wire:model.live="is_pegawai" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                        Pegawai Pemprov Jatim
                    </label>

                    @if ($is_pegawai)
                        <div>
                            <label class="ui-label">NIP</label>
                            <input type="text" wire:model="nip" class="ui-input">
                            @error('nip') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Instansi</label>
                            <x-ui.instansi-autocomplete :suggestions="$instansiSuggestions" :search="$instansiSearch" />
                        </div>
                    @endif
                @endif

                <div>
                    <label class="ui-label">Password {{ $editingId ? '(kosongkan jika tidak diubah)' : '' }}</label>
                    <input type="password" wire:model="password" class="ui-input">
                    @error('password') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ui-label">Role</label>
                        <select wire:model.live="role" class="ui-select">
                            <option value="admin">Admin</option>
                            <option value="peserta">Peserta</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                            Akun aktif
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endif
