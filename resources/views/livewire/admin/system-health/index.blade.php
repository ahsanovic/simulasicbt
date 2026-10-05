@use('App\Support\SystemHealth')

@php
    $badges = [
        SystemHealth::PASS => ['LULUS', 'bg-emerald-50 text-emerald-700'],
        SystemHealth::WARN => ['PERINGATAN', 'bg-amber-50 text-amber-700'],
        SystemHealth::FAIL => ['GAGAL', 'bg-rose-50 text-rose-700'],
        SystemHealth::PENDING => ['MENUNGGU', 'bg-sky-50 text-sky-700'],
    ];
    $overall = [
        SystemHealth::PASS => ['Semua pemeriksaan lulus', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        SystemHealth::WARN => ['Lulus dengan peringatan', 'border-amber-200 bg-amber-50 text-amber-800'],
        SystemHealth::FAIL => ['Ada pemeriksaan GAGAL — perbaiki sebelum ujian', 'border-rose-200 bg-rose-50 text-rose-800'],
    ];
@endphp

<div>
    <x-ui.page-header title="Kesehatan Sistem" description="Periksa kesiapan server sebelum ujian, pantau beban, dan simpan laporannya.">
        <button type="button" wire:click="runChecks" wire:loading.attr="disabled" wire:target="runChecks" @disabled($probeToken !== null) class="ui-btn-primary">
            <x-ui.icon name="shield-check" class="h-4 w-4" />
            <span wire:loading.remove wire:target="runChecks">Jalankan Pemeriksaan</span>
            <span wire:loading wire:target="runChecks">Memeriksa…</span>
        </button>
    </x-ui.page-header>

    @island(name: 'live-metrics')
        @php
            $dash = fn ($value, string $suffix = '') => $value === null ? '—' : $value.$suffix;
        @endphp
        <div wire:poll.30s.visible class="mb-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <x-ui.stat-card label="Peserta sedang ujian" icon="online" color="emerald"
                    :value="$dash($this->metrics['skd_in_progress']) . ' SKD · ' . $dash($this->metrics['skb_in_progress']) . ' SKB'" />
                <x-ui.stat-card label="Memori Redis" icon="bolt" color="violet"
                    :value="$dash($this->metrics['redis_used_mb'], ' MB')"
                    :trend="'batas ' . $dash($this->metrics['redis_max_mb'], ' MB') . ' · ' . $dash($this->metrics['redis_clients']) . ' koneksi'" />
                <x-ui.stat-card label="Koneksi MySQL" icon="exams" color="primary"
                    :value="$dash($this->metrics['db_connections'])"
                    :trend="'dari batas ' . $dash($this->metrics['db_max_connections'])" />
                <x-ui.stat-card label="OPcache" icon="refresh" color="amber"
                    :value="$dash($this->metrics['opcache_hit_rate'], '% hit')"
                    :trend="'memori terpakai ' . $dash($this->metrics['opcache_memory_percent'], '%')" />
                <x-ui.stat-card label="Sisa disk" icon="file"
                    :value="$dash($this->metrics['disk_free_gb'], ' GB')" />
                <x-ui.stat-card label="Job antrean gagal" icon="triangle-warning" :color="($this->metrics['failed_jobs'] ?? 0) > 0 ? 'amber' : 'slate'"
                    :value="$dash($this->metrics['failed_jobs'])" />
            </div>
            <p class="mt-2 text-xs text-slate-400">Diperbarui otomatis setiap 30 detik · {{ now()->format('H:i:s') }}</p>
        </div>
    @endisland

    <div class="ui-card mb-8 p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Hasil pemeriksaan</h2>
            @if ($lastLogId)
                <a href="{{ route('admin.system-health.report', $lastLogId) }}" target="_blank" class="ui-btn-secondary">
                    <x-ui.icon name="file" class="h-4 w-4" />
                    Cetak laporan
                </a>
            @endif
        </div>

        @if ($probeToken !== null)
            <div wire:poll.2s="checkWorkerProbe" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Menunggu worker queue memproses job uji (maks {{ SystemHealth::WORKER_TIMEOUT_SECONDS }} detik)…
            </div>
        @elseif ($this->summary)
            <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-semibold {{ $overall[$this->summary['status']][1] }}">
                {{ $overall[$this->summary['status']][0] }}
                <span class="font-normal">· {{ $this->summary['passed'] }} lulus, {{ $this->summary['warnings'] }} peringatan, {{ $this->summary['failures'] }} gagal</span>
            </div>
        @endif

        @if ($results === [])
            <p class="text-sm text-slate-500">Belum dijalankan. Tekan <strong>Jalankan Pemeriksaan</strong> untuk memeriksa Redis, cache, session, queue, dan database.</p>
        @else
            <div class="space-y-5">
                @foreach (collect($results)->groupBy('section') as $section => $items)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $section }}</h3>
                        <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                            @foreach ($items as $item)
                                <li class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-start sm:gap-3">
                                    @if ($item['status'] === SystemHealth::INFO)
                                        <span class="text-sm text-slate-500">{{ $item['label'] }}</span>
                                    @else
                                        <span class="ui-badge w-fit shrink-0 {{ $badges[$item['status']][1] }}">{{ $badges[$item['status']][0] }}</span>
                                        <div class="min-w-0 text-sm">
                                            <p class="text-slate-800">{{ $item['label'] }}</p>
                                            @if ($item['hint'] !== '')
                                                <p class="mt-0.5 text-slate-500 [overflow-wrap:anywhere]">{{ $item['hint'] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid gap-8 xl:grid-cols-2">
        <div class="ui-card min-w-0 p-6">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Log peringatan &amp; error terbaru</h2>
            @forelse ($this->logEntries as $entry)
                <div class="border-b border-slate-100 py-2 text-sm last:border-0">
                    <div class="flex items-center gap-2">
                        <span class="ui-badge {{ $entry['level'] === 'WARNING' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700' }}">{{ $entry['level'] }}</span>
                        <span class="text-xs text-slate-400">{{ $entry['time'] }}</span>
                    </div>
                    <p class="mt-1 text-slate-700 [overflow-wrap:anywhere]">{{ $entry['message'] }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">Tidak ada peringatan atau error di log.</p>
            @endforelse
        </div>

        <div class="ui-card min-w-0 p-6">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Riwayat pemeriksaan</h2>
            @forelse ($this->history as $log)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 text-sm last:border-0" wire:key="health-log-{{ $log->id }}">
                    <div class="min-w-0">
                        <span class="ui-badge {{ $badges[$log->status][1] }}">{{ $badges[$log->status][0] }}</span>
                        <span class="ml-2 text-slate-700">{{ $log->created_at->format('d M Y H:i') }}</span>
                        <span class="ml-1 text-slate-400">· {{ $log->user?->name ?? 'sistem' }}</span>
                    </div>
                    <a href="{{ route('admin.system-health.report', $log) }}" target="_blank" class="shrink-0 font-semibold text-primary-600 hover:text-primary-700">Laporan</a>
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada riwayat.</p>
            @endforelse
        </div>
    </div>
</div>
