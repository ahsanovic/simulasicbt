<div>
    <div class="mb-5">
        <a href="{{ route('admin.events.sessions', $event) }}" wire:navigate class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke kelola sesi
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Hasil Ujian — {{ $event->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Pilih jenis ujian dan sesi, lalu export sesuai kebutuhan.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($sessionId)
                    <a href="{{ $this->exportUrl(true) }}" class="ui-btn-secondary">
                        <x-ui.icon name="file" class="h-4 w-4" /> Export {{ strtoupper($type) }} — Sesi Ini
                    </a>
                @endif
                <a href="{{ $this->exportUrl(false) }}" class="ui-btn-primary">
                    <x-ui.icon name="file" class="h-4 w-4" /> Export {{ strtoupper($type) }} — Semua Sesi
                </a>
            </div>
        </div>
    </div>

    <div class="ui-card mb-5 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label class="ui-label">Jenis Ujian</label>
            <select wire:model.live="type" class="ui-select" @disabled(count($types) === 1)>
                @foreach ($types as $t)
                    <option value="{{ $t }}">{{ strtoupper($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="ui-label">Sesi</label>
            <select wire:model.live="sessionId" class="ui-select">
                <option value="">Semua Sesi</option>
                @foreach ($sessions as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[220px] flex-1">
            <label class="ui-label">Cari</label>
            <input type="text" wire:model.live.debounce.300ms="search" class="ui-input" placeholder="Nama atau NIP/NIK...">
        </div>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-3.5 text-left">#</th>
                        <th class="px-4 py-3.5 text-left">Nama</th>
                        <th class="px-4 py-3.5 text-left">NIP/NIK</th>
                        <th class="px-4 py-3.5 text-left">{{ $unitLabel }}</th>
                        <th class="px-4 py-3.5 text-left">Sesi</th>
                        <th class="px-4 py-3.5 text-left">Dikerjakan</th>
                        @if ($type === 'skb')
                            <th class="px-3 py-3.5 text-center">Benar</th>
                        @else
                            <th class="px-3 py-3.5 text-center">TWK</th>
                            <th class="px-3 py-3.5 text-center">TIU</th>
                            <th class="px-3 py-3.5 text-center">TKP</th>
                        @endif
                        <th class="px-4 py-3.5 text-center">Total</th>
                        <th class="px-4 py-3.5 text-left">Status</th>
                        <th class="px-4 py-3.5 text-left">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr wire:key="result-{{ $type }}-{{ $row['rank'] }}" class="hover:bg-slate-50/50">
                            <td class="px-3 py-3 font-semibold text-slate-400">{{ $row['rank'] }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $row['identifier'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['unit'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['session'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600"><span class="font-semibold text-slate-900">{{ $row['answered'] }}</span> / {{ $row['total'] }}</td>
                            @if ($type === 'skb')
                                <td class="px-3 py-3 text-center font-semibold tabular-nums text-slate-700">{{ $row['benar'] }}</td>
                            @else
                                <td class="px-3 py-3 text-center font-semibold tabular-nums text-slate-700">{{ $row['twk'] }}</td>
                                <td class="px-3 py-3 text-center font-semibold tabular-nums text-slate-700">{{ $row['tiu'] }}</td>
                                <td class="px-3 py-3 text-center font-semibold tabular-nums text-slate-700">{{ $row['tkp'] }}</td>
                            @endif
                            <td class="px-4 py-3 text-center text-lg font-bold tabular-nums text-slate-900">{{ $row['score'] }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match ($row['status']) {
                                        'Sedang Ujian' => 'bg-amber-100 text-amber-700',
                                        'Selesai' => 'bg-emerald-100 text-emerald-700',
                                        default => 'bg-slate-100 text-slate-500',
                                    };
                                @endphp
                                <span class="ui-badge {{ $badge }}">{{ $row['status'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $row['submitted_at'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $type === 'skb' ? 10 : 12 }}" class="px-5 py-12 text-center text-slate-500">
                            {{ filled($search) ? 'Tidak ada peserta yang cocok.' : 'Belum ada hasil ujian.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
