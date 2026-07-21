@php
    $overview = $stats['overview'];
    $hasHistory = $stats['has_history'];
    $weaknessStats = $stats['weakness_stats'];
    $pillars = $weaknessStats['pillars'] ?? [];
    $scoreTrendChart = $stats['score_trend'];
    $sectionShortcuts = [
        ['id' => 'stat-section-overview', 'label' => 'Ringkasan', 'border' => 'border-slate-200/80', 'text' => 'text-slate-700', 'hoverBorder' => 'hover:border-slate-300', 'hoverBg' => 'hover:bg-slate-50'],
        ['id' => 'stat-section-trend', 'label' => 'Grafik Skor', 'border' => 'border-primary-200/80', 'text' => 'text-primary-700', 'hoverBorder' => 'hover:border-primary-300', 'hoverBg' => 'hover:bg-primary-50'],
        ['id' => 'stat-section-pillar', 'label' => 'Pilar SKD', 'border' => 'border-indigo-200/80', 'text' => 'text-indigo-700', 'hoverBorder' => 'hover:border-indigo-300', 'hoverBg' => 'hover:bg-indigo-50'],
        ['id' => 'stat-section-materi', 'label' => 'Materi', 'border' => 'border-amber-200/80', 'text' => 'text-amber-700', 'hoverBorder' => 'hover:border-amber-300', 'hoverBg' => 'hover:bg-amber-50'],
        ['id' => 'stat-section-recommendation', 'label' => 'Rekomendasi AI', 'border' => 'border-indigo-200/80', 'text' => 'text-indigo-700', 'hoverBorder' => 'hover:border-indigo-300', 'hoverBg' => 'hover:bg-indigo-50'],
        ['id' => 'stat-section-activity', 'label' => 'Aktivitas', 'border' => 'border-teal-200/80', 'text' => 'text-teal-700', 'hoverBorder' => 'hover:border-teal-300', 'hoverBg' => 'hover:bg-teal-50'],
        ['id' => 'stat-section-competition', 'label' => 'Kompetisi', 'border' => 'border-rose-200/80', 'text' => 'text-rose-700', 'hoverBorder' => 'hover:border-rose-300', 'hoverBg' => 'hover:bg-rose-50'],
    ];
@endphp

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main
        x-data="{
            activeSection: 'stat-section-overview',
            scrollToSection(id) {
                document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            init() {
                const sections = [
                    'stat-section-overview',
                    'stat-section-trend',
                    'stat-section-pillar',
                    'stat-section-materi',
                    'stat-section-recommendation',
                    'stat-section-activity',
                    'stat-section-competition',
                ].map((id) => document.getElementById(id)).filter(Boolean);

                if (! sections.length) {
                    return;
                }

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.activeSection = entry.target.id;
                        }
                    });
                }, { rootMargin: '-30% 0px -55% 0px', threshold: 0 });

                sections.forEach((section) => observer.observe(section));
            },
        }"
        class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
    >
        <x-ui.flash-toast />

        {{-- Hero --}}
        <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-10 left-1/4 h-24 w-24 rounded-full bg-indigo-400/20"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-primary-100 ring-1 ring-white/20">
                        <svg class="h-3.5 w-3.5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Analitik Performa
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Statistik Saya</h1>
                    <p class="mt-2 max-w-2xl text-sm text-primary-100 sm:text-base">
                        Pantau progres skor, kekuatan materi, aktivitas latihan, dan pencapaian gamifikasi dalam satu tempat.
                    </p>
                </div>
                @if ($hasHistory)
                    <div class="flex flex-wrap gap-2">
                        @if ($overview['improvement'] !== null)
                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                                @if ($overview['improvement'] >= 0)
                                    <span class="text-emerald-200">↑ +{{ $overview['improvement'] }}</span>
                                @else
                                    <span class="text-rose-200">↓ {{ $overview['improvement'] }}</span>
                                @endif
                                <span class="text-primary-100">vs simulasi pertama</span>
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                            {{ number_format($overview['total_xp']) }} XP
                        </span>
                    </div>
                @endif
            </div>
        </div>

        @if (! $hasHistory)
            <div class="ui-card flex flex-col items-center px-6 py-16 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100 text-primary-600">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-bold text-slate-900">Belum Ada Data Statistik</h2>
                <p class="mt-2 max-w-md text-sm text-slate-500">Selesaikan simulasi SKD penuh pertama untuk membuka grafik progres, analisis materi, dan ringkasan performa.</p>
                <a href="{{ route('peserta.simulasi.index') }}" wire:navigate class="ui-btn-success mt-6 px-6">
                    Mulai Simulasi Pertama →
                </a>
            </div>
        @else
            {{-- Jump nav --}}
            <div class="sticky top-16 z-20 mb-8 flex flex-wrap gap-2 rounded-xl border border-primary-100 bg-white/80 p-3 shadow-sm backdrop-blur-sm">
                @foreach ($sectionShortcuts as $jump)
                    <button
                        type="button"
                        @click="scrollToSection('{{ $jump['id'] }}')"
                        :class="activeSection === '{{ $jump['id'] }}'
                            ? 'border-primary-500 bg-primary-600 text-white shadow-sm shadow-primary-500/25'
                            : 'border bg-white {{ $jump['border'] }} {{ $jump['text'] }} {{ $jump['hoverBorder'] }} {{ $jump['hoverBg'] }}'"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    >
                        @switch($jump['id'])
                            @case('stat-section-overview')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                @break
                            @case('stat-section-trend')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                                @break
                            @case('stat-section-pillar')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                @break
                            @case('stat-section-materi')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                @break
                            @case('stat-section-recommendation')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                @break
                            @case('stat-section-activity')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                @break
                            @case('stat-section-competition')
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                @break
                        @endswitch
                        {{ $jump['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Overview KPIs --}}
            <section id="stat-section-overview" class="scroll-mt-28 mb-8">
                <h2 class="mb-4 text-lg font-bold text-slate-900">Ringkasan Performa</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="ui-card p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Simulasi</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($overview['total_simulations']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="ui-card p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata Skor</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-primary-700">{{ format_exam_score($overview['average_total']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="ui-card p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Skor Terbaik</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-amber-700">{{ format_exam_score($stats['best_scores']['total']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="ui-card p-5">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tingkat Kelulusan</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-emerald-700">{{ $overview['pass_rate'] }}%</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $overview['pass_count'] }} dari {{ $overview['total_simulations'] }} simulasi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Score trend chart --}}
            <section id="stat-section-trend" class="scroll-mt-28 mb-8">
                <div class="ui-card overflow-hidden">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Progres Skor Simulasi</h2>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    @if (count($scoreTrendChart['labels']) >= 1)
                                        {{ count($scoreTrendChart['labels']) }} simulasi
                                        @if ($activeScoreTrendPeriod !== \App\Enums\ScoreTrendPeriod::All)
                                            dalam {{ strtolower($activeScoreTrendPeriod->label()) }}
                                        @else
                                            terakhir
                                        @endif
                                        · hover untuk detail · titik hijau/merah pada skor total = lulus/belum
                                    @else
                                        Pilih periode lain atau selesaikan simulasi untuk melihat grafik progres skor.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($scoreTrendPeriods as $periodOption)
                                <button
                                    type="button"
                                    wire:click="setScoreTrendPeriod('{{ $periodOption->value }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="setScoreTrendPeriod"
                                    @class([
                                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                        'bg-slate-800 text-white shadow-sm' => $activeScoreTrendPeriod === $periodOption,
                                        'bg-slate-100 text-slate-600 hover:bg-slate-200' => $activeScoreTrendPeriod !== $periodOption,
                                    ])
                                >
                                    {{ $periodOption->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if (count($scoreTrendChart['labels']) >= 1)
                        <div
                            wire:key="score-trend-{{ $activeScoreTrendPeriod->value }}"
                            data-score-trend-chart
                            class="bg-white p-4 sm:p-6"
                            x-init="$nextTick(() => window.initStatisticsCharts?.($el))"
                        >
                            <div class="mb-4 flex flex-wrap justify-end gap-1.5">
                                @foreach (['total' => 'Total', 'twk' => 'TWK', 'tiu' => 'TIU', 'tkp' => 'TKP'] as $metric => $label)
                                    <button
                                        type="button"
                                        data-score-metric="{{ $metric }}"
                                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $metric === 'total' ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/25' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            <script type="application/json" data-score-trend-payload>@json($scoreTrendChart)</script>
                            <div class="h-72 sm:h-80">
                                <canvas></canvas>
                            </div>
                        </div>
                    @else
                        <p class="px-6 py-8 text-center text-sm text-slate-500">
                            @if ($hasHistory)
                                Tidak ada simulasi dalam periode {{ strtolower($activeScoreTrendPeriod->label()) }}.
                            @else
                                Selesaikan simulasi pertama untuk melihat grafik progres skor.
                            @endif
                        </p>
                    @endif
                </div>
            </section>

            {{-- Best scores per pillar --}}
            <section id="stat-section-pillar" class="scroll-mt-28 mb-8">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="ui-card p-6">
                        <h2 class="text-lg font-bold text-slate-900">Skor Terbaik per Pilar</h2>
                        <p class="mt-1 text-sm text-slate-500">Dibandingkan ambang kelulusan SKD</p>
                        <div class="mt-6 space-y-5">
                            @foreach ($stats['pillar_comparison'] as $pillar)
                                <div wire:key="pillar-{{ $pillar['code'] }}">
                                    <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
                                        <span class="font-semibold text-slate-700">{{ $pillar['label'] }}</span>
                                        <span class="tabular-nums text-slate-600">
                                            <span class="font-bold text-slate-900">{{ format_exam_score($pillar['best']) }}</span>
                                            <span class="text-slate-400">/ {{ $pillar['max'] }}</span>
                                        </span>
                                    </div>
                                    <div class="relative h-3 overflow-hidden rounded-full bg-slate-100">
                                        @php
                                            $passPercent = $pillar['max'] > 0 ? ($pillar['passing'] / $pillar['max']) * 100 : 0;
                                            $bestPercent = $pillar['percent_of_max'];
                                        @endphp
                                        <div class="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-primary-500 to-indigo-500 transition-all"
                                             style="width: {{ $bestPercent }}%"></div>
                                        <div class="absolute inset-y-0 w-0.5 bg-amber-400" style="left: {{ $passPercent }}%" title="Ambang lulus"></div>
                                    </div>
                                    <p class="mt-1 text-xs {{ $pillar['meets_passing'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                        Ambang lulus: {{ $pillar['passing'] }}
                                        · {{ $pillar['meets_passing'] ? 'Sudah memenuhi' : 'Belum memenuhi' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if (! empty($pillars))
                        <div class="ui-card p-6">
                            <h2 class="text-lg font-bold text-slate-900">Kesiapan Materi (Pilar)</h2>
                            <p class="mt-1 text-sm text-slate-500">Akurasi jawaban dari seluruh simulasi</p>
                            <div class="mt-4">
                                <x-peserta.ai-readiness.chart-section
                                    :weakness-stats="$weaknessStats"
                                    section-id="stat-readiness-chart"
                                    size="lg"
                                />
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-3">
                                @foreach (['twk', 'tiu', 'tkp'] as $code)
                                    @php $pillar = $pillars[$code] ?? null; @endphp
                                    @if ($pillar)
                                        <div class="rounded-xl bg-slate-50 px-3 py-2.5 text-center">
                                            <p class="text-[10px] font-bold uppercase text-slate-500">{{ $pillar['label'] }}</p>
                                            <p @class([
                                                'mt-0.5 text-xl font-bold tabular-nums',
                                                'text-emerald-600' => $pillar['status'] === 'aman',
                                                'text-amber-600' => $pillar['status'] === 'cukup',
                                                'text-red-600' => $pillar['status'] === 'kritis',
                                            ])>{{ $pillar['percentage'] }}%</p>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Materials + time --}}
            <section id="stat-section-materi" class="scroll-mt-28 mb-8 grid gap-6 lg:grid-cols-2">
                <div class="ui-card p-6">
                    <h2 class="text-lg font-bold text-slate-900">Sub-Materi Lemah</h2>
                    <p class="mt-1 text-sm text-slate-500">Health bar akurasi per materi</p>
                    <div class="mt-4">
                        @if (! empty($weaknessStats['materials']))
                            <x-peserta.ai-readiness.health-bars
                                :materials="array_slice($weaknessStats['materials'], 0, 12)"
                                section-id="stat-section-health"
                                :columns="1"
                            />
                            @if (count($weaknessStats['materials']) > 12)
                                <p class="mt-3 text-xs text-slate-500">+{{ count($weaknessStats['materials']) - 12 }} materi lainnya — lihat detail di <a href="{{ route('peserta.evaluasi') }}" wire:navigate class="font-semibold text-primary-600 hover:underline">Evaluasi Kesiapan</a></p>
                            @endif
                        @else
                            <p class="text-sm text-slate-500">Data materi akan muncul setelah cukup jawaban terkumpul.</p>
                        @endif
                    </div>
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-lg font-bold text-slate-900">Manajemen Waktu</h2>
                    <p class="mt-1 text-sm text-slate-500">Kecepatan mengerjakan soal per pilar</p>
                    <div class="mt-4">
                        <x-peserta.ai-readiness.time-management
                            :time-stats="$stats['time_management']"
                            section-id="stat-section-time"
                        />
                        @if (! ($stats['time_management']['has_data'] ?? false))
                            <p class="text-sm text-slate-500">Data waktu akan muncul setelah simulasi dengan pelacakan durasi selesai.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- AI recommendation --}}
            <section id="stat-section-recommendation" class="scroll-mt-28 mb-8">
                @if ($isGenerated)
                    <div class="ui-card overflow-hidden">
                        <div class="border-b border-primary-100 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-6 py-5 sm:px-8">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                                    <svg class="h-5 w-5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold text-white sm:text-xl">Rekomendasi Belajar Personal</h2>
                                    <p class="text-sm text-primary-100/90">Dibuat oleh AI berdasarkan seluruh riwayat simulasi Anda</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 sm:p-8">
                            <x-peserta.ai-readiness.recommendation
                                :recommendation="$recommendation"
                                size="lg"
                                :show-heading="false"
                                section-id="stat-readiness-section-recommendation"
                            />
                        </div>
                    </div>

                    @include('livewire.peserta.partials.ai-readiness-action-buttons', [
                        'repeatExam' => null,
                        'needsRefresh' => $needsRefresh,
                        'inline' => true,
                        'fullWidth' => false,
                    ])
                @else
                    @include('livewire.peserta.partials.ai-readiness-pending-state', ['compact' => false])
                @endif
            </section>

            {{-- Activity + gamification --}}
            <section id="stat-section-activity" class="scroll-mt-28 mb-8">
                <h2 class="mb-4 text-lg font-bold text-slate-900">Aktivitas & Gamifikasi</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @php $activity = $stats['activity']; @endphp
                    <x-peserta.stat-mini-card label="Simulasi Penuh" :value="number_format($activity['full'])">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card label="Drill Soal" :value="number_format($activity['drill'])" icon-bg="bg-teal-100" icon-color="text-teal-600" value-class="text-2xl font-bold tabular-nums text-teal-700">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card label="Remedial" :value="number_format($activity['remedial'])" icon-bg="bg-violet-100" icon-color="text-violet-600" value-class="text-2xl font-bold tabular-nums text-violet-700">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card label="Duel" :value="number_format($activity['duel'])" icon-bg="bg-orange-100" icon-color="text-orange-600" value-class="text-2xl font-bold tabular-nums text-orange-700">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <x-peserta.stat-mini-card label="XP & Lencana" :value="number_format($stats['gamification']['total_xp']).' XP'" icon-bg="bg-amber-100" icon-color="text-amber-600" value-class="text-2xl font-bold text-slate-900">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                        </x-slot:icon>
                        <span class="block text-sm text-slate-600">{{ $stats['gamification']['devotion']['current_badge']['label'] }}</span>
                        @if (! $stats['gamification']['devotion']['is_max_tier'])
                            <span class="block mt-1">{{ number_format($stats['gamification']['devotion']['xp_to_next']) }} XP menuju {{ $stats['gamification']['devotion']['next_badge']['label'] }}</span>
                        @endif
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card label="Streak Harian" :value="($stats['gamification']['streak']['streak'] ?? 0).' hari'" icon-bg="bg-orange-100" icon-color="text-orange-600" value-class="text-2xl font-bold text-orange-600">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                        </x-slot:icon>
                        {{ $stats['gamification']['streak']['multiplier_label'] ?? '—' }} XP
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card label="Kartu Sakti" :value="$stats['flashcards']['active'].' aktif'" icon-bg="bg-yellow-100" icon-color="text-amber-600" value-class="text-2xl font-bold text-amber-600">
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </x-slot:icon>
                        {{ $stats['flashcards']['due'] }} kartu due hari ini
                    </x-peserta.stat-mini-card>
                </div>
            </section>

            {{-- Competition --}}
            <section id="stat-section-competition" class="scroll-mt-28 mb-8">
                <h2 class="mb-4 text-lg font-bold text-slate-900">Kompetisi & Posisi</h2>
                <div class="grid gap-4 lg:grid-cols-3">
                    <x-peserta.stat-mini-card
                        label="Peringkat Skor"
                        :value="$stats['leaderboard_ranks']['score'] ? '#'.$stats['leaderboard_ranks']['score'] : '—'"
                        icon-bg="bg-primary-100"
                        icon-color="text-primary-600"
                        value-class="text-3xl font-bold tabular-nums text-slate-900"
                    >
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card
                        label="Peringkat Duel"
                        :value="$stats['leaderboard_ranks']['duel'] ? '#'.$stats['leaderboard_ranks']['duel'] : '—'"
                        icon-bg="bg-rose-100"
                        icon-color="text-rose-600"
                        value-class="text-3xl font-bold tabular-nums text-slate-900"
                    >
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </x-slot:icon>
                        {{ $stats['duel']['wins'] }} menang / {{ $stats['duel']['played'] }} duel ({{ $stats['duel']['win_rate'] }}%)
                    </x-peserta.stat-mini-card>
                    <x-peserta.stat-mini-card
                        label="Hall of Fame XP"
                        :value="$stats['leaderboard_ranks']['xp'] ? '#'.$stats['leaderboard_ranks']['xp'] : '—'"
                        icon-bg="bg-violet-100"
                        icon-color="text-violet-600"
                        value-class="text-3xl font-bold tabular-nums text-slate-900"
                    >
                        <x-slot:icon>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </x-slot:icon>
                    </x-peserta.stat-mini-card>
                </div>

                @if ($stats['formation_summary'])
                    <div class="mt-4">
                        <x-peserta.formation-matchmaking-summary-card
                            variant="compact"
                            :has-history="true"
                            :summary="$stats['formation_summary']"
                        />
                    </div>
                @endif
            </section>

            {{-- Recent attempts --}}
            @if (! empty($stats['recent_attempts']))
                <section class="mb-8">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900">Aktivitas Terbaru</h2>
                        <a href="{{ route('peserta.history') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:text-primary-700">
                            Lihat riwayat lengkap →
                        </a>
                    </div>
                    <div class="ui-card divide-y divide-slate-100 overflow-hidden">
                        @foreach ($stats['recent_attempts'] as $attempt)
                            <div wire:key="recent-{{ $attempt['id'] }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-slate-50/80">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900">{{ $attempt['title'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $attempt['type'] }} · {{ $attempt['submitted_at'] }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if ($attempt['total'] !== null)
                                        <span class="text-lg font-bold tabular-nums text-slate-900">{{ format_exam_score($attempt['total']) }}</span>
                                        <span @class([
                                            'ui-badge',
                                            'bg-emerald-50 text-emerald-700' => $attempt['passed'],
                                            'bg-rose-50 text-rose-700' => ! $attempt['passed'],
                                        ])>{{ $attempt['passed'] ? 'Lulus' : 'Belum' }}</span>
                                    @endif
                                    <a href="{{ route('peserta.exam.review', $attempt['id']) }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline">
                                        Review
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </main>
</div>
