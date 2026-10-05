<div @if (! $showAddTimeModal) wire:poll.10s="pollBoard" @endif>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.events.sessions', $eventId) }}" wire:navigate class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke daftar sesi
            </a>
            <h1 class="text-2xl font-bold text-slate-900">{{ $event?->name }} — {{ $session->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $event?->exam?->title }}
                @if($event?->exam) &middot; {{ $event->exam->duration_minutes }} menit @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if ($this->eventSessions->count() > 1)
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Sesi</label>
                    <select onchange="window.location.href = this.value" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 focus:border-primary-500 focus:outline-none">
                        @foreach ($this->eventSessions as $s)
                            <option value="{{ route('admin.events.sessions.livescore', [$eventId, $s->id]) }}" @selected($s->id === $session->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($showExamTypePicker)
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jenis Ujian</label>
                    <select wire:model.live="viewMode" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 focus:border-primary-500 focus:outline-none">
                        <option value="skd">SKD</option>
                        <option value="skb">SKB</option>
                    </select>
                </div>
            @endif
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

    <div class="mb-5 grid gap-3 sm:gap-4 {{ $category === 'skd' ? 'grid-cols-3' : 'grid-cols-2 sm:grid-cols-4' }}">
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Peserta</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $this->summary['total'] }}</p>
        </div>
        @if ($category !== 'skd')
            <div class="ui-card p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Belum Mulai</p>
                <p class="mt-1 text-2xl font-bold text-slate-500">{{ $this->summary['not_started'] }}</p>
            </div>
        @endif
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Masih Ujian</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->summary['in_progress'] }}</p>
        </div>
        <div class="ui-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Selesai</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $this->summary['finished'] }}</p>
        </div>
    </div>

    {{-- Search bar --}}
    <div class="mb-5 flex gap-3">
        <div class="flex-1">
            <input type="text"
                   wire:model.live="search"
                   placeholder="Cari nama peserta atau instansi..."
                   class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500/20">
        </div>
        @if($search)
            <button wire:click="$set('search', '')" class="ui-btn-secondary px-3">Hapus</button>
        @endif
    </div>

    {{-- Toolbar: aksi massal untuk peserta terpilih --}}
    <div class="ui-card mb-5 flex flex-wrap items-center gap-3 p-4">
        @php $selectedCount = count($selected[$category]); @endphp
        <button wire:click="openAddTimeForSelected"
                @disabled($selectedCount === 0)
                @class([
                    'ui-btn-primary',
                    'opacity-50 cursor-not-allowed' => $selectedCount === 0,
                ])>
            Tambah Waktu {{ strtoupper($category) }} Terpilih ({{ $selectedCount }})
        </button>

        <span class="hidden h-6 w-px bg-slate-200 sm:block"></span>

        <button wire:click="resetSelected"
                wire:confirm="Reset ujian peserta terpilih? Semua jawaban terhapus dan ujian dimulai dari awal."
                @disabled($selectedCount === 0)
                @class([
                    'ui-btn-secondary text-rose-600 hover:bg-rose-50',
                    'opacity-50 cursor-not-allowed' => $selectedCount === 0,
                ])>
            Reset Ujian {{ strtoupper($category) }} Terpilih ({{ $selectedCount }})
        </button>

        <p class="w-full text-xs text-slate-500">
            Centang peserta di tabel (atau "centang semua" di header). <strong>Tambah waktu</strong> hanya berlaku bagi yang masih ujian dan tidak boleh membuat sisa waktu melebihi durasi ujian;
            <strong>reset</strong> bisa untuk siapa saja — termasuk yang sudah selesai/kehabisan waktu, mis. saat jam perangkat tidak sesuai.
            @if ($showExamTypePicker)
                Pilihan peserta SKD dan SKB tersimpan terpisah — berpindah "Jenis Ujian" tidak akan menghapus centang di papan yang lain.
            @endif
        </p>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-4 py-3.5 text-left">
                            <input type="checkbox" wire:model.live="selectAll.{{ $category }}"
                                   title="Centang semua peserta {{ strtoupper($category) }}"
                                   class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                        </th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Peserta</th>
                        @if ($category === 'skb')
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jabatan</th>
                        @endif
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dikerjakan</th>
                        @if ($category === 'skb')
                            <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Benar</th>
                        @else
                            <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TWK</th>
                            <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TIU</th>
                            <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TKP</th>
                        @endif
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Waktu</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->rows as $i => $row)
                        <tr wire:key="{{ $row['row_key'] }}" class="transition hover:bg-slate-50/50">
                            <td class="px-4 py-4">
                                @if ($row['attempt_id'] !== null)
                                    <input type="checkbox" wire:model.live="selected.{{ $category }}" value="{{ $row['attempt_id'] }}"
                                           class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                                @endif
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $row['name'] }}</p>
                                @if ($category !== 'skb' && $row['instansi'])
                                    <p class="text-xs text-slate-500">{{ $row['instansi'] }}</p>
                                @endif
                            </td>
                            @if ($category === 'skb')
                                <td class="px-4 py-4 text-sm text-slate-600">{{ $row['jabatan'] }}</td>
                            @endif
                            <td class="px-4 py-4 text-slate-600">
                                <span class="font-semibold text-slate-900">{{ $row['answered'] }}</span> / {{ $row['total'] }}
                            </td>
                            @if ($category === 'skb')
                                <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['benar'] }}</td>
                            @else
                                <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['twk'] }}</td>
                                <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['tiu'] }}</td>
                                <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['tkp'] }}</td>
                            @endif
                            <td class="px-4 py-4 text-center">
                                <span class="text-lg font-bold tabular-nums text-slate-900">{{ $row['score'] }}</span>
                            </td>
                            <td class="px-4 py-4">
                                @if($row['in_progress'])
                                    <span class="font-mono font-semibold tabular-nums text-slate-700">{{ $row['remaining'] }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if ($row['attempt_id'] === null)
                                    <span class="ui-badge bg-slate-100 text-slate-500">Belum Mulai</span>
                                @elseif($row['in_progress'])
                                    <span class="ui-badge bg-amber-100 text-amber-700">Masih Ujian</span>
                                @else
                                    <span class="ui-badge bg-emerald-100 text-emerald-700">
                                        Selesai{{ $row['submitted_at'] ? ' · '.$row['submitted_at'] : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                @if ($row['attempt_id'] !== null)
                                    @if($row['in_progress'])
                                        <button wire:click="openAddTime({{ $row['attempt_id'] }})" class="ui-btn-ghost px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">
                                            + Waktu
                                        </button>
                                    @endif
                                    <button wire:click="resetAttempt({{ $row['attempt_id'] }})"
                                            wire:confirm="Reset ujian {{ $row['name'] }}? Semua jawaban terhapus dan ujian dimulai dari awal."
                                            class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">
                                        Reset
                                    </button>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @php $colspan = $category === 'skb' ? 10 : 11; @endphp
                        <tr><td colspan="{{ $colspan }}" class="px-5 py-12 text-center text-slate-500">
                            @if($search)
                                Tidak ada peserta yang cocok dengan pencarian "{{ $search }}".
                            @else
                                Belum ada peserta yang bergabung ke event ini.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($this->totalPages > 1)
        <div class="mt-4 flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3">
            <div class="text-xs text-slate-600">
                Menampilkan {{ $this->perPage * ($this->currentPage - 1) + 1 }} - {{ min($this->perPage * $this->currentPage, count($this->filteredRows)) }} dari {{ count($this->filteredRows) }} peserta
            </div>
            <div class="flex gap-2">
                <button wire:click="goToPage({{ $this->currentPage - 1 }})"
                        @disabled($this->currentPage === 1)
                        @class(['ui-btn-secondary px-3 py-1.5 text-sm', 'opacity-50 cursor-not-allowed' => $this->currentPage === 1])>
                    ← Sebelumnya
                </button>
                <div class="flex items-center gap-1">
                    @for($i = 1; $i <= $this->totalPages; $i++)
                        <button wire:click="goToPage({{ $i }})"
                                @class([
                                    'px-2.5 py-1.5 text-sm font-medium rounded-md transition',
                                    'bg-primary-600 text-white' => $this->currentPage === $i,
                                    'text-slate-600 hover:bg-slate-200' => $this->currentPage !== $i,
                                ])>
                            {{ $i }}
                        </button>
                    @endfor
                </div>
                <button wire:click="goToPage({{ $this->currentPage + 1 }})"
                        @disabled($this->currentPage === $this->totalPages)
                        @class(['ui-btn-secondary px-3 py-1.5 text-sm', 'opacity-50 cursor-not-allowed' => $this->currentPage === $this->totalPages])>
                    Selanjutnya →
                </button>
            </div>
        </div>
    @endif

    {{-- Popup tambah waktu: menampilkan batas maksimal yang boleh ditambahkan --}}
    @if ($showAddTimeModal)
        @php $ctx = $this->addTimeContext; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeAddTimeModal"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Tambah Waktu Ujian {{ $ctx['board_label'] }}</h2>
                        <p class="text-xs text-slate-500">Hanya memperpanjang ujian {{ $ctx['board_label'] }} — tidak memengaruhi ujian {{ $ctx['board_label'] === 'SKD' ? 'SKB' : 'SKD' }} peserta ini.</p>
                    </div>
                    <button type="button" wire:click="closeAddTimeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4 p-6">
                    <div class="rounded-xl bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500">Untuk</span>
                            <span class="text-right font-semibold text-slate-900">{{ $ctx['label'] }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-slate-500">Durasi ujian</span>
                            <span class="font-semibold text-slate-900">{{ $ctx['duration'] }} menit</span>
                        </div>
                        @if (! is_null($ctx['remaining']))
                            <div class="mt-2 flex justify-between gap-4">
                                <span class="text-slate-500">Sisa waktu sekarang</span>
                                <span class="font-semibold text-slate-900">{{ $ctx['remaining'] }} menit</span>
                            </div>
                        @endif
                    </div>

                    @if ($ctx['max'] <= 0)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            Sisa waktu sudah mencapai durasi ujian ({{ $ctx['duration'] }} menit), jadi <strong>tidak ada waktu yang bisa ditambahkan</strong>.
                            Gunakan <strong>Reset Ujian</strong> bila peserta perlu mengulang dari awal.
                        </div>
                    @else
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800">
                            Maksimal yang bisa ditambahkan{{ $ctx['is_bulk'] ? ' (mengikuti peserta dengan sisa waktu terbanyak)' : '' }}:
                            <strong>{{ $ctx['max'] }} menit</strong>.
                            <span class="block text-xs text-indigo-700/80">Sisa waktu peserta tidak boleh melebihi durasi ujian.</span>
                        </div>

                        <div>
                            <label class="ui-label">Tambah berapa menit?</label>
                            {{-- Deferred: typing sends nothing; the value goes with "Tambah Waktu"
                                 (clamped again on the server). It used to re-render the whole board per keystroke. --}}
                            <input type="number" min="1" max="{{ $ctx['max'] }}" wire:model="addMinutes"
                                   x-on:change="const v = Math.max(1, Math.min({{ $ctx['max'] }}, parseInt($el.value) || 1)); if (String(v) !== $el.value) { $el.value = v; $el.dispatchEvent(new Event('input')); }"
                                   class="ui-input w-32 text-center">
                            <p class="mt-1.5 text-xs text-slate-500">Otomatis dibatasi maksimal {{ $ctx['max'] }} menit.</p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" wire:click="closeAddTimeModal" class="ui-btn-secondary">Batal</button>
                    <button type="button" wire:click="confirmAddTime" wire:loading.attr="disabled"
                            @disabled($ctx['max'] <= 0)
                            @class([
                                'ui-btn-primary',
                                'opacity-50 cursor-not-allowed' => $ctx['max'] <= 0,
                            ])>
                        Tambah Waktu
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
