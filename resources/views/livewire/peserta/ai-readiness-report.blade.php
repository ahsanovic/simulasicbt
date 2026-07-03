<div class="ui-card relative flex flex-col overflow-hidden border-primary-200/50 bg-gradient-to-b from-white via-primary-50/10 to-indigo-50/20 shadow-lg shadow-primary-100/20">
    {{-- Header --}}
    <div class="relative overflow-hidden border-b border-primary-100/80 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-4 py-3.5">
        <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-6 left-1/3 h-14 w-14 rounded-full bg-indigo-400/20"></div>

        <div class="relative flex items-center gap-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                <svg class="h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-bold tracking-tight text-white">AI Evaluasi & Rapor Kesiapan CPNS</h3>
                <p class="text-[10px] font-medium text-primary-100/90">Analisis kelemahan berbasis seluruh riwayat</p>
            </div>
        </div>
    </div>

    <div class="flex-1 p-4">
        @if (($weaknessStats['total_simulations'] ?? 0) === 0)
            <div class="flex flex-col items-center justify-center py-8 text-center">
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
            <div class="space-y-4">
                <div data-readiness-chart data-stats='@json($weaknessStats)' class="mx-auto max-w-[240px]">
                    <canvas height="220"></canvas>
                </div>

                @if (! empty($weaknessStats['materials']))
                    <div class="space-y-2.5">
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

                <div class="rounded-xl border border-primary-100 bg-white/80 p-3.5">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-primary-600">Rekomendasi AI</p>
                    <div class="prose prose-sm max-w-none text-[13px] leading-relaxed text-slate-700 whitespace-pre-line">{{ format_ai_recommendation($recommendation) }}</div>
                </div>

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
        @else
            {{-- State 1 & 2: Initial / Loading --}}
            <div class="flex flex-col items-center py-6 text-center">
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
