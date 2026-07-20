<div>
    <x-ui.page-header title="Event Offline" description="Buat event, atur sesi & kode per sesi, pantau livescore, dan tarik data peserta.">
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Event
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama event..." class="ui-input pl-10">
            </div>
        </x-ui.filter-toolbar>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Event</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Paket Ujian</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sesi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr wire:key="event-{{ $event->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $event->name }}</p>
                                @if($event->description)
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $event->description }}</p>
                                @endif
                                @if($event->public_livescore)
                                    <div class="mt-1 flex items-center gap-1">
                                        <a href="{{ route('public.livescore.show', $event) }}" target="_blank"
                                           title="{{ route('public.livescore.show', $event) }}"
                                           class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-semibold text-sky-700 hover:bg-sky-100">
                                            <x-ui.icon name="online" class="h-3 w-3" /> /livescore/{{ $event->public_code }} ↗
                                        </a>
                                        <button wire:click="regeneratePublicCode({{ $event->id }})"
                                                wire:confirm="Buat ulang link publik? Link lama tidak akan berlaku lagi."
                                                title="Buat ulang link publik"
                                                class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                            <x-ui.icon name="refresh" class="h-3 w-3" />
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ $event->exam?->title ?? '—' }}
                                @if($event->exam)
                                    <span class="block text-xs text-slate-400">{{ $event->exam->duration_minutes }} mnt</span>
                                @endif
                            </td>
                            <td class="px-5 py-4"><span class="ui-badge bg-indigo-50 text-indigo-700">{{ $event->sessions_count }} sesi</span></td>
                            <td class="px-5 py-4"><span class="ui-badge bg-slate-100 text-slate-700">{{ $event->attempts_count }}</span></td>
                            <td class="px-5 py-4">
                                @php
                                    $statusColor = match($event->status->value) {
                                        'active' => 'bg-emerald-100 text-emerald-700',
                                        'draft' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <span class="ui-badge {{ $statusColor }}">{{ $event->status->label() }}</span>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('admin.events.sessions', $event) }}" wire:navigate class="ui-btn-ghost px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">Kelola Sesi</a>
                                <a href="{{ route('admin.events.export', $event) }}" class="ui-btn-ghost px-3 py-1.5 text-emerald-600 hover:bg-emerald-50">Export</a>
                                <button wire:click="openEditModal({{ $event->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                                <button wire:click="delete({{ $event->id }})" wire:confirm="Hapus event ini beserta semua sesinya?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada event dibuat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $events->links() }}</div>
        @endif
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Event' : 'Buat Event Baru' }}</h2>
                    <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <form wire:submit="save" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama Event</label>
                        <input type="text" wire:model="name" class="ui-input" placeholder="mis. Tryout Akbar Dinas Kesehatan 2026">
                        @error('name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Paket Ujian</label>
                        <select wire:model="exam_id" class="ui-select">
                            <option value="">— Pilih paket ujian —</option>
                            @foreach ($exams as $exam)
                                <option value="{{ $exam->id }}">{{ $exam->title }}</option>
                            @endforeach
                        </select>
                        @error('exam_id') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <p class="mt-1.5 text-xs text-slate-500">Semua sesi dalam event ini memakai paket ujian yang sama.</p>
                    </div>
                    <div>
                        <label class="ui-label">Status Event</label>
                        <select wire:model="status" class="ui-select">
                            <option value="draft">Draft</option>
                            <option value="active">Aktif</option>
                            <option value="closed">Ditutup</option>
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500">Peserta hanya bisa masuk jika event <strong>Aktif</strong> dan sesinya juga aktif. Kode & jadwal diatur per sesi di halaman "Kelola Sesi".</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="public_livescore" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                            <span>
                                <span class="text-sm font-semibold text-slate-900">Tampilkan livescore ke publik</span>
                                <span class="block text-xs text-slate-500">Jika aktif, livescore event ini bisa dibuka tanpa login lewat halaman publik (cocok untuk layar/proyektor).</span>
                            </span>
                        </label>
                    </div>
                    <div>
                        <label class="ui-label">Deskripsi (opsional)</label>
                        <textarea wire:model="description" rows="2" class="ui-input"></textarea>
                    </div>
                    @unless ($editingId)
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-3 text-sm text-indigo-800">
                            Event baru otomatis dibuatkan <strong>Sesi 1</strong>. Tambah sesi lain & atur kodenya di "Kelola Sesi".
                        </div>
                    @endunless
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan Event</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
