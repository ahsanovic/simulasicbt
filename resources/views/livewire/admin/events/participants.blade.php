<div>
    <x-ui.page-header title="Peserta Mode Ujian — {{ $event->name }}" description="Peserta login pakai NIK yang diimpor di sini.">
        <a href="{{ route('admin.events.index') }}" wire:navigate class="ui-btn-secondary">Kembali ke Event</a>
        <button wire:click="openCreateModal" class="ui-btn-secondary">Tambah Peserta</button>
        <button wire:click="$set('showImportModal', true)" class="ui-btn-primary">Import Peserta</button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau NIK..." class="ui-input pl-10">
            </div>
            <div class="w-full sm:w-56">
                <select wire:model.live="sessionFilter" class="ui-select">
                    <option value="">Semua sesi</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
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
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIK</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sesi</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($participants as $participant)
                        <tr wire:key="participant-{{ $participant->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $participant->name }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $participant->nik }}</td>
                            <td class="px-5 py-4">
                                {{ $participant->jabatan_label }}
                                @if (! $participant->formation_id && ! $participant->jabatan_skb_id)
                                    <span class="ui-badge ml-1 bg-rose-50 text-rose-600">Jabatan tidak cocok</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($participant->eventSession)
                                    <span class="ui-badge bg-indigo-50 text-indigo-700">{{ $participant->eventSession->name }}</span>
                                @else
                                    <span class="ui-badge bg-slate-100 text-slate-500">Belum diset</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <button wire:click="openEditModal({{ $participant->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                                <button wire:click="delete({{ $participant->id }})" wire:confirm="Hapus peserta ini dari event?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada peserta diimpor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($participants->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $participants->links() }}</div>
        @endif
    </div>

    <x-admin.import-excel-modal
        :show="$showImportModal"
        title="Import Peserta"
        description="Unduh template, isi Nama/NIK/Jabatan/Sesi, lalu unggah."
        :form-action="route('admin.events.participants.import', $event)"
        :template-route="route('admin.events.participants.import-template', $event)"
        max-size="20 MB"
    >
        Kolom <strong>jabatan</strong> harus cocok persis dengan nama {{ $event->exam_mode->includesSkb() ? 'Jabatan SKB' : 'Jabatan (Formasi)' }} yang sudah terdaftar, dan kolom <strong>sesi</strong> harus cocok persis dengan nama sesi di halaman "Kelola Sesi"
        ({{ $sessions->isNotEmpty() ? $sessions->pluck('name')->implode(', ') : 'belum ada sesi dibuat' }}).
        Satu file bisa berisi peserta untuk beberapa sesi sekaligus. NIK dipakai sebagai login peserta.
    </x-admin.import-excel-modal>
    <x-ui.import-error-modal :show="$showImportErrorModal" :report="$importErrorReport" />

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeFormModal"></div>
            <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Peserta' : 'Tambah Peserta' }}</h2>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit="saveForm" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama</label>
                        <input type="text" wire:model="editName" class="ui-input">
                        @error('editName') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">NIK</label>
                        <input type="text" wire:model="editNik" class="ui-input font-mono">
                        @error('editNik') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Sesi</label>
                        <select wire:model="editSessionId" class="ui-select">
                            <option value="">— Belum diset —</option>
                            @foreach ($sessions as $session)
                                <option value="{{ $session->id }}">{{ $session->name }}</option>
                            @endforeach
                        </select>
                        @error('editSessionId') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        @if ($event->exam_mode->includesSkb())
                            <label class="ui-label">Jabatan SKB</label>
                            <x-ui.jabatan-skb-autocomplete
                                :suggestions="$jabatanSkbSuggestions"
                                :search="$jabatanSkbSearch"
                            />
                            @error('editJabatanSkbId') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @else
                            <label class="ui-label">Jabatan (Formasi)</label>
                            <x-ui.formation-autocomplete
                                :suggestions="$formationSuggestions"
                                :search="$formationSearch"
                            />
                            @error('editFormationId') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @endif
                        <p class="mt-1.5 text-xs text-slate-500">Ketik buat cari, lalu <strong>wajib klik</strong> salah satu hasil untuk memilih. Jabatan yang belum ada di bank soal tidak bisa disimpan.</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeFormModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
