@props([
    'value',
    'threshold',
    'color' => 'slate',
    'empty' => '0',
])

@php
    $score = $value !== null && $value !== '' ? (int) round((float) $value) : (int) $empty;
    $passes = $score >= $threshold;

    $scoreColor = match ($color) {
        'blue' => 'text-blue-700',
        'amber' => 'text-amber-700',
        'violet' => 'text-violet-700',
        'primary' => 'text-primary-700',
        default => 'text-slate-700',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-center gap-0.5']) }}>
    <span class="text-base font-bold tabular-nums {{ $scoreColor }}">{{ $score }}</span>
    <span @class([
        'inline-flex items-center gap-0.5 text-[10px] font-semibold leading-none',
        'text-emerald-600' => $passes,
        'text-red-600' => ! $passes,
    ])>
        @if ($passes)
            <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            ≥ {{ $threshold }}
        @else
            <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            ≥ {{ $threshold }}
        @endif
    </span>
</div>
