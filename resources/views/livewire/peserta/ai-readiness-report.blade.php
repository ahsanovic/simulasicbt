<div id="feature-readiness-card" @class([
    'ui-card relative flex max-h-[calc(100dvh-5rem)] flex-col overflow-hidden border-primary-200/50 bg-gradient-to-b from-white via-primary-50/10 to-indigo-50/20 shadow-lg shadow-primary-100/20 lg:max-h-[calc(100dvh-6rem)]',
    'ui-tour-pointer' => $focusHighlight === 'time-management'
        && ! ($isGenerated && ($weaknessStats['time_management']['has_data'] ?? false)),
])>
    {{-- Header --}}
    <div @class([
        'relative shrink-0 overflow-hidden border-b border-primary-100/80 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-4 py-3.5 rounded-t-2xl',
        'ui-tour-pointer ui-tour-pointer--inset' => $focusHighlight === 'readiness',
    ])
         id="feature-readiness-header">
        <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-6 left-1/3 h-14 w-14 rounded-full bg-indigo-400/20"></div>

        <div class="relative flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                <svg class="h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-bold tracking-tight text-white">Evaluasi & Rapor Kesiapan CPNS Berbasis AI</h3>
                <p class="text-[10px] font-medium text-primary-100/90">Analisis kelemahan & manajemen waktu dari seluruh riwayat ujian</p>
            </div>
        </div>
    </div>

    <div class="flex min-h-0 flex-1 flex-col">
        @if (($weaknessStats['total_simulations'] ?? 0) === 0)
            <div class="flex flex-1 flex-col items-center justify-center overflow-y-auto p-4 py-8 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-indigo-50 text-primary-500 shadow-inner">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-700">Analisis belum tersedia</p>
                <p class="mt-2 max-w-[220px] text-xs leading-relaxed text-slate-500">
                    Selesaikan simulasi pertama untuk membuka analisis kelemahan dan rekomendasi belajar berbasis AI!
                </p>
            </div>
        @elseif ($isGenerated)
            {{-- State 3: Generated --}}
            <div
                x-data="{
                    scrollToSection(id) {
                        const target = this.$refs.panel?.querySelector('#' + id);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    },
                }"
                class="flex min-h-0 flex-1 flex-col"
            >
                <div class="shrink-0 border-b border-primary-100/70 bg-gradient-to-b from-white to-primary-50/30 px-3 py-2.5">
                    <p class="mb-2 flex items-center justify-center gap-1.5 text-[10px] font-medium text-slate-400">
                        <svg class="h-3.5 w-3.5 shrink-0 animate-bounce text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                        Konten bisa digulir — lompat ke bagian
                        <svg class="h-3.5 w-3.5 shrink-0 animate-bounce text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </p>
                    <div class="flex flex-wrap justify-center gap-1.5">
                        <button type="button"
                                @click="scrollToSection('readiness-section-chart')"
                                class="inline-flex items-center gap-1 rounded-lg border border-primary-200/80 bg-white px-2.5 py-1 text-[11px] font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                            Grafik
                        </button>
                        @if (! empty($weaknessStats['materials']))
                            <button type="button"
                                    @click="scrollToSection('readiness-section-health')"
                                    class="inline-flex items-center gap-1 rounded-lg border border-amber-200/80 bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                Health Bar
                            </button>
                        @endif
                        <button type="button"
                                @click="scrollToSection('readiness-section-recommendation')"
                                class="inline-flex items-center gap-1 rounded-lg border border-indigo-200/80 bg-white px-2.5 py-1 text-[11px] font-semibold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            Rekomendasi AI
                        </button>
                        @if (($weaknessStats['time_management']['has_data'] ?? false))
                            <button type="button"
                                    id="feature-time-management-button"
                                    @click="scrollToSection('readiness-section-time')"
                                    @class([
                                        'inline-flex items-center gap-1 rounded-lg border border-orange-200/80 bg-white px-2.5 py-1 text-[11px] font-semibold text-orange-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50',
                                        'ui-tour-pointer' => $focusHighlight === 'time-management',
                                    ])>
                                <span aria-hidden="true">⏱️</span>
                                Manajemen Waktu
                            </button>
                        @endif
                    </div>
                </div>

                <div x-ref="panel" class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-y-contain scroll-smooth p-4 pb-3">
                    <div id="readiness-section-chart" class="scroll-mt-2">
                        <div data-readiness-chart data-stats='@json($weaknessStats)' class="mx-auto max-w-[240px] shrink-0">
                            <canvas height="220"></canvas>
                        </div>
                    </div>

                    @if (! empty($weaknessStats['materials']))
                        <div id="readiness-section-health" class="scroll-mt-2 space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Health Bar Sub-Materi</p>
                            @foreach ($weaknessStats['materials'] as $material)
                                <div wire:key="material-{{ md5($material['display_name']) }}" class="space-y-1">
                                    <div class="flex items-center justify-between gap-2 text-[11px]">
                                        <span class="min-w-0 truncate font-medium text-slate-700" title="{{ $material['display_name'] }}">
                                            {{ $material['display_name'] }}
                                        </span>
                                        <span @class([
                                            'shrink-0 font-bold tabular-nums',
                                            'text-emerald-600' => $material['status'] === 'aman',
                                            'text-amber-600' => $material['status'] === 'cukup',
                                            'text-red-600' => $material['status'] === 'kritis',
                                        ])>{{ $material['percentage'] }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div @class([
                                            'h-full rounded-full transition-all duration-500',
                                            'bg-emerald-500' => $material['status'] === 'aman',
                                            'bg-amber-400' => $material['status'] === 'cukup',
                                            'bg-red-500' => $material['status'] === 'kritis',
                                        ]) style="width: {{ $material['percentage'] }}%"></div>
                                    </div>
                                    <p @class([
                                        'text-[10px] font-medium',
                                        'text-emerald-600' => $material['status'] === 'aman',
                                        'text-amber-600' => $material['status'] === 'cukup',
                                        'text-red-600' => $material['status'] === 'kritis',
                                    ])>{{ $material['status_label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (($weaknessStats['time_management']['has_data'] ?? false))
                        @php
                            $timeStats = $weaknessStats['time_management'];
                            $safeSeconds = $timeStats['safe_seconds_per_question'];
                        @endphp
                        <div id="readiness-section-time" class="scroll-mt-2 space-y-3 rounded-xl border border-orange-100 bg-orange-50/40 p-3.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-orange-700">⏱️ Analisis Manajemen Waktu</p>
                            <p class="text-xs text-slate-600">
                                Rata-rata kecepatan per pilar dari {{ $timeStats['total_exams_with_data'] }} simulasi (batas aman: {{ $safeSeconds }} detik/soal)
                            </p>
                            <div class="grid gap-2">
                                @foreach ($timeStats['average_seconds_by_pillar'] as $code => $average)
                                    @php
                                        $percent = $safeSeconds > 0 ? min(100, (int) round(($average / $safeSeconds) * 100)) : 0;
                                        $isSlow = $average > $safeSeconds;
                                    @endphp
                                    <div wire:key="time-pillar-{{ $code }}" class="space-y-1">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="font-semibold text-slate-700">{{ strtoupper($code) }}</span>
                                            <span @class([
                                                'font-bold tabular-nums',
                                                'text-rose-600' => $isSlow,
                                                'text-emerald-600' => ! $isSlow,
                                            ])>{{ $average }} dtk/soal</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-white">
                                            <div @class([
                                                'h-full rounded-full transition-all duration-500',
                                                'bg-rose-500' => $isSlow,
                                                'bg-emerald-500' => ! $isSlow,
                                            ]) style="width: {{ $percent }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if ($timeStats['early_phase_average'] && $timeStats['late_phase_average'])
                                <p class="text-xs leading-relaxed text-slate-600">
                                    Ritme awal (25% soal pertama): <strong>{{ $timeStats['early_phase_average'] }} dtk/soal</strong>
                                    · Ritme akhir (25% terakhir): <strong>{{ $timeStats['late_phase_average'] }} dtk/soal</strong>
                                </p>
                            @endif
                        </div>
                    @endif

                    <div id="readiness-section-recommendation" class="scroll-mt-2 rounded-xl border border-primary-100 bg-white/80 p-3.5">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-primary-600">Rekomendasi AI</p>
                        <div class="prose prose-sm max-w-none text-[13px] leading-relaxed text-slate-700 whitespace-pre-line">{{ format_ai_recommendation($recommendation) }}</div>
                    </div>
                </div>
            </div>

            @if ($repeatExam || $needsRefresh)
                <div class="shrink-0 space-y-2 border-t border-primary-100/80 bg-gradient-to-t from-white/95 to-white/70 p-4 pt-3">
                    @if ($repeatExam)
                        <button wire:click="repeatSimulation"
                                wire:loading.attr="disabled"
                                wire:target="repeatSimulation"
                                class="ui-btn-success h-10 w-full whitespace-nowrap">
                            <span wire:loading wire:target="repeatSimulation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                            <span wire:loading.remove wire:target="repeatSimulation" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Ulangi Simulasi →
                            </span>
                            <span wire:loading wire:target="repeatSimulation">Memulai...</span>
                        </button>
                    @endif

                    @if ($needsRefresh)
                        <button wire:click="generateRecommendation"
                                wire:loading.attr="disabled"
                                wire:target="generateRecommendation"
                                class="ui-btn-primary h-10 w-full whitespace-nowrap">
                            <span wire:loading wire:target="generateRecommendation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                            <span wire:loading.remove wire:target="generateRecommendation">Perbarui Rekomendasi AI ✨</span>
                            <span wire:loading wire:target="generateRecommendation">Memperbarui...</span>
                        </button>
                    @endif
                </div>
            @endif
        @else
            {{-- State 1 & 2: Initial / Loading --}}
            <div class="flex flex-1 flex-col items-center overflow-y-auto overscroll-y-contain p-4 py-6 text-center">
                <div class="relative flex h-20 w-20 items-center justify-center">
                    <div class="absolute inset-0 animate-pulse rounded-full bg-gradient-to-br from-primary-100 to-indigo-100"></div>
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-500 to-indigo-600 text-white shadow-lg shadow-primary-300/40">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>

                <p class="mt-5 text-sm font-semibold text-slate-800">
                    @if ($needsRefresh)
                        Ada simulasi baru sejak analisis terakhir
                    @else
                        Ingin tahu materi apa saja yang membuat nilaimu belum lolos passing grade?
                    @endif
                </p>
                <p class="mt-2 max-w-[240px] text-xs leading-relaxed text-slate-500">
                    @if ($needsRefresh)
                        Perbarui analisis AI agar rekomendasi belajar mengikuti performa terbarumu.
                    @else
                        Biarkan AI menganalisis seluruh riwayat ujianmu.
                    @endif
                </p>

                @if ($error)
                    <div class="mt-4 w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-left">
                        <p class="text-xs font-medium text-red-700">{{ $error }}</p>
                    </div>
                @endif

                <button wire:click="generateRecommendation"
                        wire:loading.attr="disabled"
                        wire:target="generateRecommendation"
                        @class([
                            'ui-btn-primary mt-5 h-10 w-full whitespace-nowrap',
                            'opacity-70' => $isLoading,
                        ])>
                    <span wire:loading wire:target="generateRecommendation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                    <span wire:loading.remove wire:target="generateRecommendation">
                        {{ $needsRefresh ? 'Perbarui Rekomendasi AI ✨' : 'Minta Rekomendasi AI ✨' }}
                    </span>
                    <span wire:loading wire:target="generateRecommendation">AI sedang menganalisis...</span>
                </button>

                {{-- Skeleton loading overlay --}}
                <div wire:loading wire:target="generateRecommendation" class="mt-5 w-full space-y-3">
                    <div class="h-3 animate-pulse rounded-full bg-slate-200"></div>
                    <div class="h-3 w-5/6 animate-pulse rounded-full bg-slate-200"></div>
                    <div class="h-3 w-4/6 animate-pulse rounded-full bg-slate-200"></div>
                    <div class="h-20 animate-pulse rounded-xl bg-gradient-to-r from-slate-100 via-slate-200 to-slate-100"></div>
                </div>
            </div>
        @endif
    </div>
</div>
