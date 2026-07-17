<div wire:poll.5s>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.events.sessions', $event) }}" wire:navigate class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke daftar sesi
            </a>
            <h1 class="text-2xl font-bold text-slate-900">{{ $event->name }} — {{ $session->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $event->exam?->title }}
                @if($event->exam) &middot; {{ $event->exam->duration_minutes }} menit @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-indigo-50 px-4 py-2 text-center">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-500">Kode Sesi</p>
                <p class="font-mono text-xl font-bold tracking-widest text-indigo-700">{{ $session->code }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-rose-500"></span>
                </span>
                LIVE
            </span>
        </div>
    </div>

    <x-ui.flash-toast />

    <div class="mb-5 grid grid-cols-3 gap-3 sm:gap-4">
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Peserta</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $this->summary['total'] }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Masih Ujian</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->summary['in_progress'] }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Selesai</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $this->summary['finished'] }}</p>
        </div>
    </div>

    {{-- Toolbar: tambah waktu untuk peserta terpilih --}}
    <div class="ui-card mb-5 flex flex-wrap items-center gap-3 p-4">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-700">Tambah waktu</label>
            <input type="number" min="1" max="180" wire:model="addMinutes" class="ui-input w-20 text-center">
            <span class="text-sm text-slate-500">menit</span>
        </div>
        <button wire:click="addTimeToSelected"
                @disabled(count($selected) === 0)
                @class([
                    'ui-btn-primary',
                    'opacity-50 cursor-not-allowed' => count($selected) === 0,
                ])>
            Tambah ke Terpilih ({{ count($selected) }})
        </button>
        <p class="text-xs text-slate-500">Centang peserta di tabel, atau gunakan "centang semua" pada header untuk memilih semua yang masih ujian.</p>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-4 py-3.5 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                   title="Centang semua peserta yang masih ujian"
                                   class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                        </th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Peserta</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dikerjakan</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Skor</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Waktu</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->rows as $i => $row)
                        <tr wire:key="score-{{ $row['attempt_id'] }}" class="transition hover:bg-slate-50/50">
                            <td class="px-4 py-4">
                                @if($row['in_progress'])
                                    <input type="checkbox" wire:model.live="selected" value="{{ $row['attempt_id'] }}"
                                           class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                                @endif
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $row['name'] }}</p>
                                @if($row['instansi'])
                                    <p class="text-xs text-slate-500">{{ $row['instansi'] }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <span class="font-semibold text-slate-900">{{ $row['answered'] }}</span> / {{ $row['total'] }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-lg font-bold text-slate-900">{{ $row['score'] }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if($row['in_progress'])
                                    <span class="font-mono font-semibold tabular-nums text-slate-700">{{ $row['remaining'] }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($row['in_progress'])
                                    <span class="ui-badge bg-amber-100 text-amber-700">Masih Ujian</span>
                                @else
                                    <span class="ui-badge bg-emerald-100 text-emerald-700">
                                        Selesai{{ $row['submitted_at'] ? ' · '.$row['submitted_at'] : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                @if($row['in_progress'])
                                    <button wire:click="addTime({{ $row['attempt_id'] }})" class="ui-btn-ghost px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">
                                        + Waktu
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-500">Belum ada peserta yang bergabung ke event ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
