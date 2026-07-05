<div>
    <x-ui.page-header title="Laporan" description="Statistik ringkas aktivitas simulasi ujian." />

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach([
            ['label' => 'Total Pengguna', 'value' => number_format($report['total_users']), 'color' => 'primary', 'icon' => 'users'],
            ['label' => 'Total Peserta', 'value' => number_format($report['total_peserta']), 'color' => 'emerald', 'icon' => 'users'],
            ['label' => 'Bank Soal', 'value' => number_format($report['total_questions']), 'color' => 'amber', 'icon' => 'questions'],
            ['label' => 'Ujian Selesai', 'value' => number_format($report['completed_attempts']), 'color' => 'violet', 'icon' => 'exams'],
            ['label' => 'Rata-rata Skor', 'value' => format_exam_score($report['average_score']), 'color' => 'primary', 'icon' => 'reports'],
        ] as $item)
            <x-ui.stat-card
                :label="$item['label']"
                :value="$item['value']"
                :color="$item['color']"
                :icon="$item['icon']"
            />
        @endforeach
    </div>

    <div class="mt-8">
        <x-ui.page-header
            title="Statistik Kelulusan Ambang Batas"
            description="Rekap peserta yang memenuhi nilai ambang batas SKD pada seluruh simulasi yang diselesaikan."
        />

        <x-exam-passing-grades-banner :passing-grades="$passingGrades" class="mb-5" />

        <div class="grid gap-5 sm:grid-cols-3">
            <x-ui.stat-card
                label="Total Hasil Ujian"
                :value="number_format($passingStats['total'])"
                color="primary"
                trend="Simulasi yang sudah diselesaikan"
                icon="results"
            />
            <x-ui.stat-card
                label="Lulus Semua Komponen"
                :value="number_format($passingStats['passed'])"
                color="emerald"
                trend="Memenuhi TWK, TIU, TKP, dan Total"
                icon="exams"
            />
            <x-ui.stat-card
                label="Tingkat Kelulusan"
                :value="$passingStats['total'] > 0 ? round(($passingStats['passed'] / $passingStats['total']) * 100).'%' : '0%'"
                color="amber"
                :trend="$passingStats['total'] > 0 ? number_format($passingStats['passed']).' dari '.number_format($passingStats['total']).' simulasi' : 'Belum ada data'"
                icon="reports"
            />
        </div>

        <div class="ui-card mt-5 overflow-hidden p-0">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-bold text-slate-900">Kelulusan per Komponen</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah simulasi yang memenuhi ambang batas masing-masing komponen</p>
            </div>
            <div class="grid gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    ['key' => 'twk', 'label' => 'TWK', 'passed' => $passingStats['twk_passed'], 'threshold' => $passingGrades['twk'], 'badge' => 'bg-blue-50 text-blue-700', 'bar' => 'bg-blue-500'],
                    ['key' => 'tiu', 'label' => 'TIU', 'passed' => $passingStats['tiu_passed'], 'threshold' => $passingGrades['tiu'], 'badge' => 'bg-amber-50 text-amber-700', 'bar' => 'bg-amber-500'],
                    ['key' => 'tkp', 'label' => 'TKP', 'passed' => $passingStats['tkp_passed'], 'threshold' => $passingGrades['tkp'], 'badge' => 'bg-violet-50 text-violet-700', 'bar' => 'bg-violet-500'],
                    ['key' => 'total', 'label' => 'Total', 'passed' => $passingStats['total_score_passed'], 'threshold' => $passingGrades['total'], 'badge' => 'bg-primary-50 text-primary-700', 'bar' => 'bg-primary-600'],
                ] as $component)
                    @php
                        $rate = $passingStats['total'] > 0 ? round(($component['passed'] / $passingStats['total']) * 100) : 0;
                    @endphp
                    <div class="bg-white p-5">
                        <div class="flex items-center justify-between gap-2">
                            <span @class(['ui-badge', $component['badge']])>{{ $component['label'] }} ≥ {{ $component['threshold'] }}</span>
                            <span class="text-xs font-semibold text-slate-500">{{ $rate }}%</span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-slate-900">
                            {{ number_format($component['passed']) }}
                            <span class="text-sm font-medium text-slate-400">/ {{ number_format($passingStats['total']) }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">simulasi lulus komponen ini</p>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="{{ $component['bar'] }} h-full rounded-full transition-all" style="width: {{ $rate }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-8">
        <x-ui.page-header
            title="Statistik Pendaftaran Peserta"
            description="Jumlah peserta yang sudah mendaftar simulasi, dapat difilter per instansi."
        >
            <a
                href="{{ route('admin.reports.export-participants', $instansiFilter ? ['instansi' => $instansiFilter] : []) }}"
                class="ui-btn-success"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download Excel
            </a>
        </x-ui.page-header>

        <div class="ui-card mb-5 p-4 sm:p-5">
            <x-ui.filter-toolbar>
                <select wire:model.live="instansiFilter" class="ui-select w-1/2 sm:w-80 sm:shrink-0">
                    <option value="">Semua Instansi</option>
                    @foreach ($instansis as $instansi)
                        <option value="{{ $instansi->id }}">{{ $instansi->nama }}</option>
                    @endforeach
                </select>
            </x-ui.filter-toolbar>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card
                label="Total Terdaftar"
                :value="number_format($registrationStats['total'])"
                color="primary"
                :trend="$instansiFilter ? 'Peserta di instansi terpilih' : 'Seluruh peserta terdaftar'"
                icon="users"
            />
            <x-ui.stat-card
                label="Pegawai Pemprov"
                :value="number_format($registrationStats['pegawai'])"
                color="emerald"
                trend="Daftar dengan NIP & instansi"
                icon="office"
            />
            <x-ui.stat-card
                label="Peserta Umum"
                :value="number_format($registrationStats['peserta_umum'])"
                color="violet"
                trend="Daftar via Google"
                icon="users"
            />
            <x-ui.stat-card
                label="Akun Aktif"
                :value="number_format($registrationStats['aktif'])"
                color="amber"
                trend="Peserta dengan status aktif"
                icon="exams"
            />
        </div>

        @if ($instansiFilter && $participants)
            <div class="ui-card mt-5 overflow-hidden p-0">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-900">Daftar Peserta</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Peserta terdaftar di
                        <span class="font-medium text-slate-700">{{ $instansis->firstWhere('id', $instansiFilter)?->nama }}</span>
                    </p>
                </div>
                <div class="ui-table-wrap">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80">
                                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">NIP</th>
                                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Email</th>
                                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Terdaftar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($participants as $participant)
                                    <tr wire:key="participant-{{ $participant->id }}" class="transition hover:bg-slate-50/50">
                                        <td class="px-5 py-4 font-semibold text-slate-900">{{ $participant->name }}</td>
                                        <td class="px-5 py-4 text-slate-600">{{ $participant->nip ?? '—' }}</td>
                                        <td class="px-5 py-4 text-slate-600">{{ $participant->email }}</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'ui-badge',
                                                'bg-emerald-100 text-emerald-700' => $participant->is_active,
                                                'bg-rose-100 text-rose-700' => ! $participant->is_active,
                                            ])>{{ $participant->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">{{ $participant->created_at->translatedFormat('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada peserta di instansi ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($participants->hasPages())
                        <div class="border-t border-slate-100 px-5 py-3">{{ $participants->links(null, ['scrollTo' => false]) }}</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="ui-card mt-5 overflow-hidden p-0">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-bold text-slate-900">Rekap per Instansi</h2>
                <p class="mt-1 text-sm text-slate-500">Distribusi peserta pegawai berdasarkan instansi tempat bekerja</p>
            </div>
            <div class="ui-table-wrap">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/80">
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Instansi</th>
                                <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah Peserta</th>
                                <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Persentase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($instansiStats as $instansi)
                                <tr
                                    wire:key="instansi-stat-{{ $instansi->id }}"
                                    @class([
                                        'transition hover:bg-slate-50/50',
                                        'bg-primary-50/60' => $instansiFilter === $instansi->id,
                                    ])
                                >
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900">{{ $instansi->nama }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <span class="ui-badge bg-primary-100 text-primary-700">{{ number_format($instansi->peserta_count) }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right text-slate-600">
                                        @if ($report['total_peserta'] > 0)
                                            {{ number_format(($instansi->peserta_count / $report['total_peserta']) * 100, 1) }}%
                                        @else
                                            0%
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50/50">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900">Peserta Umum</p>
                                    <p class="text-xs text-slate-500">Tanpa instansi · daftar via Google</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span class="ui-badge bg-violet-100 text-violet-700">{{ number_format($pesertaUmumCount) }}</span>
                                </td>
                                <td class="px-5 py-4 text-right text-slate-600">
                                    @if ($report['total_peserta'] > 0)
                                        {{ number_format(($pesertaUmumCount / $report['total_peserta']) * 100, 1) }}%
                                    @else
                                        0%
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-slate-200 bg-slate-50/80 font-semibold">
                                <td class="px-5 py-4 text-slate-900">Total Keseluruhan</td>
                                <td class="px-5 py-4 text-right text-slate-900">{{ number_format($report['total_peserta']) }}</td>
                                <td class="px-5 py-4 text-right text-slate-600">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-card mt-8 p-6">
        <h2 class="text-base font-bold text-slate-900">Ringkasan Performa</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
            Gunakan data di atas untuk mengevaluasi partisipasi peserta, tingkat kelulusan ambang batas SKD,
            dan kualitas bank soal. Modul <a href="{{ route('admin.results.index') }}" wire:navigate class="font-semibold text-primary-600 hover:underline">Hasil Ujian</a>
            menyediakan detail skor per peserta beserta status lulus per komponen TWK, TIU, TKP, dan Total.
        </p>
    </div>
</div>
