@php
    $pillars = $weaknessStats['pillars'] ?? [];
@endphp

<div
    x-data="{
        scrollToSection(id) {
            const target = document.getElementById(id);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
    }"
    class="space-y-6"
>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="ui-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Simulasi</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($weaknessStats['total_simulations'] ?? 0) }}</p>
        </div>
        @foreach (['twk', 'tiu', 'tkp'] as $code)
            @php $pillar = $pillars[$code] ?? null; @endphp
            @if ($pillar)
                <div class="ui-card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kesiapan {{ $pillar['label'] }}</p>
                    <p @class([
                        'mt-1 text-3xl font-bold tabular-nums',
                        'text-emerald-600' => $pillar['status'] === 'aman',
                        'text-amber-600' => $pillar['status'] === 'cukup',
                        'text-red-600' => $pillar['status'] === 'kritis',
                    ])>{{ $pillar['percentage'] }}%</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $pillar['status_label'] }}</p>
                </div>
            @endif
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2 rounded-xl border border-primary-100 bg-white/80 p-3 shadow-sm">
        <button type="button" @click="scrollToSection('readiness-section-chart')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-primary-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
            Grafik Pilar
        </button>
        @if (! empty($weaknessStats['materials']))
            <button type="button" @click="scrollToSection('readiness-section-health')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Health Bar
            </button>
        @endif
        @if (($weaknessStats['time_management']['has_data'] ?? false))
            <button type="button" id="feature-time-management-button" @click="scrollToSection('readiness-section-time')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg border border-orange-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-orange-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50',
                        'ui-tour-pointer' => $focusHighlight === 'time-management',
                    ])>
                <span aria-hidden="true">⏱️</span>
                Manajemen Waktu
            </button>
        @endif
        <button type="button" @click="scrollToSection('readiness-section-recommendation')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            Rekomendasi AI
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="ui-card p-6 sm:p-8">
            <div class="mb-6 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Grafik Kesiapan Pilar</h2>
                    <p class="text-sm text-slate-500">Perbandingan TWK, TIU, dan TKP dari seluruh simulasi</p>
                </div>
            </div>
            <x-peserta.ai-readiness.chart-section :weakness-stats="$weaknessStats" size="lg" />
        </div>

        <div class="ui-card p-6 sm:p-8">
            <div class="mb-6 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Health Bar Sub-Materi</h2>
                    <p class="text-sm text-slate-500">Tingkat penguasaan tiap sub-materi berdasarkan jawaban Anda</p>
                </div>
            </div>
            <x-peserta.ai-readiness.health-bars :materials="$weaknessStats['materials'] ?? []" :columns="2" />

            @if ($this->weakSeedPreview['available'] > 0)
                <div class="mt-6 rounded-2xl border border-dashed border-amber-200 bg-amber-50/50 p-5">
                    <p class="text-sm font-bold text-amber-900">Kartu Sakti — Auto-Seed Materi Lemah</p>
                    <p class="mt-1 text-xs text-amber-800/80">
                        Simpan {{ $this->weakSeedPreview['available'] }} soal dari materi yang masih lemah ke Kartu Sakti untuk review harian otomatis.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button"
                                wire:click="seedWeakMaterialsToFlashcard"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600">
                            <span wire:loading.remove wire:target="seedWeakMaterialsToFlashcard">⭐ Simpan ke Kartu Sakti</span>
                            <span wire:loading wire:target="seedWeakMaterialsToFlashcard">Menyimpan...</span>
                        </button>
                        <a href="{{ route('peserta.kartu-sakti.index') }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-50">
                            Buka Kartu Sakti →
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (($weaknessStats['time_management']['has_data'] ?? false))
        <div @class([
            'ui-card p-6 sm:p-8',
            'ui-tour-pointer' => $focusHighlight === 'time-management',
        ]) id="readiness-section-time">
            <div class="mb-6 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Analisis Kecepatan Berpikir</h2>
                    <p class="text-sm text-slate-500">Pola manajemen waktu dari seluruh riwayat simulasi Anda</p>
                </div>
            </div>
            <x-peserta.ai-readiness.time-management :time-stats="$weaknessStats['time_management'] ?? []" section-id="readiness-section-time-inner" />
        </div>
    @endif

    <div id="feature-readiness-card" @class([
        'ui-card overflow-hidden',
        'ui-tour-pointer ui-tour-pointer--inset' => $focusHighlight === 'readiness',
    ])>
        <div id="feature-readiness-header" class="border-b border-primary-100 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-6 py-5 sm:px-8">
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
                section-id="readiness-section-recommendation"
            />
        </div>
    </div>

    @include('livewire.peserta.partials.ai-readiness-action-buttons', [
        'repeatExam' => $repeatExam,
        'needsRefresh' => $needsRefresh,
        'inline' => true,
        'fullWidth' => false,
    ])
</div>
