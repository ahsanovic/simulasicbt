<div>
    <div class="mb-5">
        <a href="{{ route('admin.events.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke daftar event
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $event->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $event->exam?->title }} @if($event->exam) &middot; {{ $event->exam->duration_minutes }} menit @endif
                    &middot; Status event: <span class="font-semibold">{{ $event->status->label() }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.events.export', $event) }}" class="ui-btn-secondary">
                    <x-ui.icon name="file" class="h-4 w-4" /> Export Semua Sesi
                </a>
                <button wire:click="openCreateModal" class="ui-btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Sesi
                </button>
            </div>
        </div>
    </div>

    <x-ui.flash-toast />

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sesi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kode</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jadwal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $session->name }}</td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-mono text-sm font-bold tracking-widest text-indigo-700">{{ $session->code }}</span>
                                    <button wire:click="regenerateCode({{ $session->id }})" wire:confirm="Buat ulang kode sesi? Kode lama tidak berlaku lagi." title="Buat ulang kode" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                        <x-ui.icon name="refresh" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-500">
                                @if($session->starts_at)
                                    {{ $session->starts_at->translatedFormat('d M Y H:i') }}@if($session->ends_at)<br>s/d {{ $session->ends_at->translatedFormat('d M Y H:i') }}@endif
                                @else
                                    <span class="text-slate-400">Tanpa jadwal</span>
                                @endif
                            </td>
                            <td class="px-5 py-4"><span class="ui-badge bg-slate-100 text-slate-700">{{ $session->attempts_count }}</span></td>
                            <td class="px-5 py-4">
                                @php
                                    $statusColor = match($session->status->value) {
                                        'active' => 'bg-emerald-100 text-emerald-700',
                                        'draft' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <button wire:click="cycleStatus({{ $session->id }})" title="Ubah status" class="ui-badge {{ $statusColor }} cursor-pointer">{{ $session->status->label() }}</button>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('admin.events.sessions.livescore', [$event, $session]) }}" wire:navigate class="ui-btn-ghost px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">Livescore</a>
                                <a href="{{ route('admin.events.sessions.export', [$event, $session]) }}" class="ui-btn-ghost px-3 py-1.5 text-emerald-600 hover:bg-emerald-50">Export</a>
                                <button wire:click="openEditModal({{ $session->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                                <button wire:click="delete({{ $session->id }})" wire:confirm="Hapus sesi ini?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">Hapus</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada sesi. Klik "Tambah Sesi".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Sesi' : 'Tambah Sesi' }}</h2>
                    <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <form wire:submit="save" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama Sesi</label>
                        <input type="text" wire:model="name" class="ui-input" placeholder="mis. Sesi 1 (Pagi)">
                        @error('name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label">Mulai (opsional)</label>
                            <input type="datetime-local" wire:model="starts_at" class="ui-input">
                            @error('starts_at') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label">Selesai (opsional)</label>
                            <input type="datetime-local" wire:model="ends_at" class="ui-input">
                            @error('ends_at') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="ui-label">Status</label>
                        <select wire:model="status" class="ui-select">
                            <option value="draft">Draft</option>
                            <option value="active">Aktif</option>
                            <option value="closed">Ditutup</option>
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500">Kode gabung dibuat otomatis. Peserta bisa masuk hanya jika sesi <strong>Aktif</strong> (dan dalam rentang jadwal).</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan Sesi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
