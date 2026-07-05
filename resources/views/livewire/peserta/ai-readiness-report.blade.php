@if ($variant === 'sidebar')
    <div id="feature-readiness-card" @class([
        'ui-card relative flex max-h-[calc(100dvh-5rem)] flex-col overflow-hidden border-primary-200/50 bg-gradient-to-b from-white via-primary-50/10 to-indigo-50/20 shadow-lg shadow-primary-100/20 lg:max-h-[calc(100dvh-6rem)]',
        'ui-tour-pointer' => $focusHighlight === 'time-management'
            && ! ($isGenerated && ($weaknessStats['time_management']['has_data'] ?? false)),
    ])>
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
                <x-peserta.ai-readiness.empty-state compact />
            @elseif ($isGenerated)
                @include('livewire.peserta.partials.ai-readiness-generated-sidebar')
            @else
                @include('livewire.peserta.partials.ai-readiness-pending-state', ['compact' => true])
            @endif
        </div>
    </div>
@elseif ($variant === 'full')
    @if (($weaknessStats['total_simulations'] ?? 0) === 0)
        <x-peserta.ai-readiness.empty-state />
    @elseif ($isGenerated)
        @include('livewire.peserta.partials.ai-readiness-generated-full')
    @else
        @include('livewire.peserta.partials.ai-readiness-pending-state', ['compact' => false])
    @endif
@endif
