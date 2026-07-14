<div @if ($activeExport?->status->isActive()) wire:poll.5s @endif>
    <x-ui.page-header title="Hasil Ujian" description="Pantau skor TWK, TIU, TKP, dan total nilai peserta beserta status kelulusan ambang batas.">
        <button
            type="button"
            wire:click="requestExport"
            wire:loading.attr="disabled"
            wire:target="requestExport"
            @disabled($exportRowCount === 0)
            class="ui-btn-success min-w-[9.5rem] disabled:cursor-not-allowed disabled:opacity-60"
        >
            <svg wire:loading.remove wire:target="requestExport" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <svg wire:loading wire:target="requestExport" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span wire:loading.remove wire:target="requestExport">Export CSV</span>
            <span wire:loading wire:target="requestExport">Memproses...</span>
        </button>
    </x-ui.page-header>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @error('export')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $message }}
        </div>
    @enderror

    @if ($activeExport)
        <div @class([
            'mb-5 rounded-xl border px-4 py-4',
            'border-blue-200 bg-blue-50' => $activeExport->status->isActive(),
            'border-emerald-200 bg-emerald-50' => $activeExport->status === \App\Enums\ExportRequestStatus::Completed,
            'border-red-200 bg-red-50' => $activeExport->status === \App\Enums\ExportRequestStatus::Failed,
        ])>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p @class([
                        'text-sm font-semibold',
                        'text-blue-900' => $activeExport->status->isActive(),
                        'text-emerald-900' => $activeExport->status === \App\Enums\ExportRequestStatus::Completed,
                        'text-red-900' => $activeExport->status === \App\Enums\ExportRequestStatus::Failed,
                    ])>
                        Export Hasil Ujian — {{ $activeExport->status->label() }}
                    </p>
                    <p @class([
                        'mt-1 text-sm',
                        'text-blue-800' => $activeExport->status->isActive(),
                        'text-emerald-800' => $activeExport->status === \App\Enums\ExportRequestStatus::Completed,
                        'text-red-800' => $activeExport->status === \App\Enums\ExportRequestStatus::Failed,
                    ])>
                        @if ($activeExport->status->isActive())
                            Sedang menyiapkan {{ number_format($activeExport->total_rows ?? $exportRowCount) }} baris data sesuai filter aktif.
                            @if ($activeExport->isQueueStale())
                                <span class="mt-1 block text-xs font-medium text-amber-800">
                                    Export masih menunggu antrian. Kemungkinan queue worker belum berjalan di server.
                                </span>
                            @endif
                        @elseif ($activeExport->status === \App\Enums\ExportRequestStatus::Completed)
                            @if ($activeExport->isDownloadable())
                                File siap diunduh ({{ number_format($activeExport->total_rows ?? 0) }} baris). Link berlaku hingga {{ $activeExport->expires_at?->format('d M Y, H:i') }}.
                            @else
                                Export selesai, tetapi file belum tersedia di server ini.
                                <span class="mt-1 block text-xs opacity-80">Pastikan queue worker dan web server memakai folder storage yang sama.</span>
                            @endif
                        @else
                            {{ $activeExport->error_message ?? 'Export gagal diproses.' }}
                            <span class="mt-1 block text-xs opacity-80">Detail teknis tercatat di log server (storage/logs/laravel.log).</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($activeExport->status === \App\Enums\ExportRequestStatus::Completed && $activeExport->isDownloadable())
                        <a
                            href="{{ route('admin.results.exports.download', $activeExport) }}"
                            class="ui-btn-success"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Unduh CSV
                        </a>
                    @endif

                    @unless ($activeExport->status->isActive())
                        <button type="button" wire:click="dismissExport" class="ui-btn-secondary">
                            Tutup
                        </button>
                    @endunless
                </div>
            </div>
        </div>
    @endif

    <div @class(['mb-5 grid gap-4', 'sm:grid-cols-2' => $examTypeFilter !== 'duel', 'sm:grid-cols-1' => $examTypeFilter === 'duel'])>
        <x-ui.stat-card
            label="Total Hasil Ujian"
            :value="number_format($stats['total'])"
            color="primary"
            trend="Simulasi yang sudah diselesaikan"
            icon="results"
        />
        @if ($examTypeFilter !== 'duel')
            <x-ui.stat-card
                label="Lulus Ambang Batas"
                :value="number_format($stats['passed'])"
                color="emerald"
                :trend="$stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100).'% dari total hasil' : 'Belum ada data'"
                icon="exams"
            />
        @endif
    </div>

    @if ($examTypeFilter !== 'duel')
        <x-exam-passing-grades-banner :passing-grades="$passingGrades" class="mb-5" />
    @endif

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama peserta..." class="ui-input pl-10">
            </div>
            <select wire:model.live="examTypeFilter" class="ui-select w-full sm:w-52 sm:shrink-0">
                <option value="">Semua Jenis Ujian</option>
                <option value="simulasi">Simulasi</option>
                <option value="duel">Duel Mini-Tryout</option>
            </select>
            <input type="date" wire:model.live="dateFrom" class="ui-input w-full sm:w-40 sm:shrink-0" title="Dari tanggal selesai">
            <input type="date" wire:model.live="dateTo" class="ui-input w-full sm:w-40 sm:shrink-0" title="Sampai tanggal selesai">
        </x-ui.filter-toolbar>
        <p class="mt-3 text-xs text-slate-500">
            Filter tanggal berdasarkan waktu ujian selesai. Export CSV akan mengikuti filter yang aktif
            @if ($exportRowCount > 0)
                ({{ number_format($exportRowCount) }} baris).
            @else
                — tidak ada data untuk diekspor.
            @endif
        </p>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ujian</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TWK</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TIU</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TKP</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($attempts as $attempt)
                        @php
                            $isDuel = (bool) ($attempt->exam?->isDuel() ?? false);
                            $isDuelWinner = $isDuel && $attempt->duelSession?->winner_user_id === $attempt->user_id;
                            $isDuelDraw = $isDuel && $attempt->duelSession?->winner_user_id === null;
                            $passes = exam_attempt_passes(
                                $attempt->score_twk,
                                $attempt->score_tiu,
                                $attempt->score_tkp,
                                $attempt->total_score,
                            );
                        @endphp
                        <tr wire:key="attempt-{{ $attempt->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">{{ $attempt->user->initials() }}</div>
                                    <span class="font-semibold text-slate-900">{{ $attempt->user->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $attempt->exam->title }}</td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_twk" :threshold="$passingGrades['twk']" color="blue" :show-threshold="! $isDuel" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_tiu" :threshold="$passingGrades['tiu']" color="amber" :show-threshold="! $isDuel" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_tkp" :threshold="$passingGrades['tkp']" color="violet" :show-threshold="! $isDuel" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->total_score" :threshold="$passingGrades['total']" color="primary" :show-threshold="! $isDuel" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($isDuel)
                                    <span @class([
                                        'ui-badge inline-flex items-center gap-1',
                                        'bg-emerald-100 text-emerald-700' => $isDuelWinner,
                                        'bg-slate-100 text-slate-600' => $isDuelDraw,
                                        'bg-rose-100 text-rose-700' => ! $isDuelWinner && ! $isDuelDraw,
                                    ])>
                                        @if ($isDuelWinner)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Menang
                                        @elseif ($isDuelDraw)
                                            Seri
                                        @else
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Kalah
                                        @endif
                                    </span>
                                @else
                                    <span @class([
                                        'ui-badge inline-flex items-center gap-1',
                                        'bg-emerald-100 text-emerald-700' => $passes,
                                        'bg-red-100 text-red-700' => ! $passes,
                                    ])>
                                        @if ($passes)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Lulus
                                        @else
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Belum Lulus
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $attempt->submitted_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-slate-500">Belum ada hasil ujian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attempts->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $attempts->links() }}</div>
        @endif
    </div>
</div>
