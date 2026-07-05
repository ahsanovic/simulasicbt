@props([
    'compact' => false,
])

<div @class([
    'flex flex-col items-center justify-center text-center',
    'flex-1 overflow-y-auto p-4 py-8' => $compact,
    'ui-card px-6 py-16' => ! $compact,
])>
    <div @class([
        'flex items-center justify-center rounded-2xl bg-gradient-to-br from-primary-100 to-indigo-50 text-primary-500 shadow-inner',
        'h-16 w-16' => $compact,
        'h-20 w-20' => ! $compact,
    ])>
        <svg @class(['h-8 w-8' => $compact, 'h-10 w-10' => ! $compact]) fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>
    <p @class(['mt-4 font-semibold text-slate-700', 'text-sm' => $compact, 'text-base' => ! $compact])>Analisis belum tersedia</p>
    <p @class([
        'mt-2 leading-relaxed text-slate-500',
        'max-w-[220px] text-xs' => $compact,
        'max-w-md text-sm' => ! $compact,
    ])>
        Selesaikan simulasi pertama untuk membuka analisis kelemahan dan rekomendasi belajar berbasis AI!
    </p>
    @if (! $compact)
        <a href="{{ route('peserta.dashboard') }}" wire:navigate class="ui-btn-primary mt-6 inline-flex items-center gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Mulai Simulasi
        </a>
    @endif
</div>
