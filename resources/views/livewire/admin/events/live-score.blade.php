<div @if (! $showAddTimeModal) wire:poll.5s @endif>
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

    {{-- Toolbar: aksi massal untuk peserta terpilih --}}
    <div class="ui-card mb-5 flex flex-wrap items-center gap-3 p-4">
        <button wire:click="openAddTimeForSelected"
                @disabled(count($selected) === 0)
                @class([
                    'ui-btn-primary',
                    'opacity-50 cursor-not-allowed' => count($selected) === 0,
                ])>
            Tambah Waktu Terpilih ({{ count($selected) }})
        </button>

        <span class="hidden h-6 w-px bg-slate-200 sm:block"></span>

        <button wire:click="resetSelected"
                wire:confirm="Reset ujian peserta terpilih? Semua jawaban terhapus dan ujian dimulai dari awal."
                @disabled(count($selected) === 0)
                @class([
                    'ui-btn-secondary text-rose-600 hover:bg-rose-50',
                    'opacity-50 cursor-not-allowed' => count($selected) === 0,
                ])>
            Reset Ujian Terpilih ({{ count($selected) }})
        </button>

        <p class="w-full text-xs text-slate-500">
            Centang peserta di tabel (atau "centang semua" di header). <strong>Tambah waktu</strong> hanya berlaku bagi yang masih ujian dan tidak boleh membuat sisa waktu melebihi durasi ujian;
            <strong>reset</strong> bisa untuk siapa saja — termasuk yang sudah selesai/kehabisan waktu, mis. saat jam perangkat tidak sesuai.
        </p>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-4 py-3.5 text-left">
                            <input type="checkbox" wire:model.live="selectAll"
                                   title="Centang semua peserta"
                                   class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                        </th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama Peserta</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dikerjakan</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TWK</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TIU</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TKP</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Waktu</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->rows as $i => $row)
                        <tr wire:key="score-{{ $row['attempt_id'] }}" class="transition hover:bg-slate-50/50">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selected" value="{{ $row['attempt_id'] }}"
                                       class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
                            </td>
                            <td class="px-3 py-4 font-semibold text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">{{ $row['name'] }}</p>
                                @if($row['instansi'])
                                    <p class="text-xs text-slate-500">{{ $row['instansi'] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                <span class="font-semibold text-slate-900">{{ $row['answered'] }}</span> / {{ $row['total'] }}
                            </td>
                            <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['twk'] }}</td>
                            <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['tiu'] }}</td>
                            <td class="px-3 py-4 text-center font-semibold tabular-nums text-slate-700">{{ $row['tkp'] }}</td>
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
                                    <button wire:click="openAddTime({{ $row['attempt_id'] }})" class="ui-btn-ghost px-3 py-1.5 text-indigo-600 hover:bg-indigo-50">
                                        + Waktu
                                    </button>
                                @endif
                                <button wire:click="resetAttempt({{ $row['attempt_id'] }})"
                                        wire:confirm="Reset ujian {{ $row['name'] }}? Semua jawaban terhapus dan ujian dimulai dari awal."
                                        class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">
                                    Reset
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-5 py-12 text-center text-slate-500">Belum ada peserta yang bergabung ke event ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Popup tambah waktu: menampilkan batas maksimal yang boleh ditambahkan --}}
    @if ($showAddTimeModal)
        @php $ctx = $this->addTimeContext; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeAddTimeModal"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Tambah Waktu Ujian</h2>
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
                            <input type="number" min="1" max="{{ $ctx['max'] }}" wire:model.live="addMinutes" class="ui-input w-32 text-center">
                            <p class="mt-1.5 text-xs text-slate-500">Otomatis dibatasi maksimal {{ $ctx['max'] }} menit.</p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                    <button type="button" wire:click="closeAddTimeModal" class="ui-btn-secondary">Batal</button>
                    <button type="button" wire:click="confirmAddTime"
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
