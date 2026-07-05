<div @class([
    'flex flex-col items-center text-center',
    'flex-1 overflow-y-auto overscroll-y-contain p-4 py-6' => $compact ?? false,
    'ui-card px-6 py-12 sm:px-10 sm:py-14' => ! ($compact ?? false),
])>
    <div class="relative flex h-20 w-20 items-center justify-center">
        <div class="absolute inset-0 animate-pulse rounded-full bg-gradient-to-br from-primary-100 to-indigo-100"></div>
        <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-500 to-indigo-600 text-white shadow-lg shadow-primary-300/40">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
    </div>

    <p @class(['mt-5 font-semibold text-slate-800', 'text-sm' => $compact ?? false, 'text-lg' => ! ($compact ?? false)])>
        @if ($needsRefresh)
            Ada simulasi baru sejak analisis terakhir
        @else
            Ingin tahu materi apa saja yang membuat nilaimu belum lolos passing grade?
        @endif
    </p>
    <p @class([
        'mt-2 leading-relaxed text-slate-500',
        'max-w-[240px] text-xs' => $compact ?? false,
        'max-w-lg text-sm' => ! ($compact ?? false),
    ])>
        @if ($needsRefresh)
            Perbarui analisis AI agar rekomendasi belajar mengikuti performa terbarumu.
        @else
            Biarkan AI menganalisis seluruh riwayat ujianmu.
        @endif
    </p>

    @if ($error)
        <div @class(['mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-left', 'w-full' => $compact ?? false, 'w-full max-w-md' => ! ($compact ?? false)])>
            <p class="text-xs font-medium text-red-700">{{ $error }}</p>
        </div>
    @endif

    <button wire:click="generateRecommendation"
            wire:loading.attr="disabled"
            wire:target="generateRecommendation"
            @class([
                'ui-btn-primary mt-5 whitespace-nowrap',
                'h-10 w-full' => $compact ?? false,
                'h-11 px-8 text-base' => ! ($compact ?? false),
                'opacity-70' => $isLoading,
            ])>
        <span wire:loading wire:target="generateRecommendation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
        <span wire:loading.remove wire:target="generateRecommendation">
            {{ $needsRefresh ? 'Perbarui Rekomendasi AI ✨' : 'Minta Rekomendasi AI ✨' }}
        </span>
        <span wire:loading wire:target="generateRecommendation">AI sedang menganalisis...</span>
    </button>

    <div wire:loading wire:target="generateRecommendation" @class(['mt-5 space-y-3', 'w-full' => $compact ?? false, 'w-full max-w-md' => ! ($compact ?? false)])>
        <div class="h-3 animate-pulse rounded-full bg-slate-200"></div>
        <div class="h-3 w-5/6 animate-pulse rounded-full bg-slate-200"></div>
        <div class="h-3 w-4/6 animate-pulse rounded-full bg-slate-200"></div>
        <div class="h-20 animate-pulse rounded-xl bg-gradient-to-r from-slate-100 via-slate-200 to-slate-100"></div>
    </div>
</div>
