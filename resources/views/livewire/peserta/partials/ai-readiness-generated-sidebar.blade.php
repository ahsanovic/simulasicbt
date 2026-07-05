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
        <x-peserta.ai-readiness.chart-section :weakness-stats="$weaknessStats" />
        <x-peserta.ai-readiness.health-bars :materials="$weaknessStats['materials'] ?? []" />
        <x-peserta.ai-readiness.time-management :time-stats="$weaknessStats['time_management'] ?? []" />
        <x-peserta.ai-readiness.recommendation :recommendation="$recommendation" />
    </div>
</div>

@include('livewire.peserta.partials.ai-readiness-action-buttons', [
    'repeatExam' => $repeatExam,
    'needsRefresh' => $needsRefresh,
])
