@props([
    'value',
    'threshold',
    'max' => 100,
    'label',
    'color' => 'primary',
])

@php
    $score = $value !== null && $value !== '' ? (int) round((float) $value) : null;
    $passes = $score !== null && $score >= $threshold;
    $pct = $score !== null ? min(100, round(($score / $max) * 100)) : 0;
    $thresholdPct = min(100, round(($threshold / $max) * 100));

    $barColor = match ($color) {
        'blue' => 'bg-blue-500',
        'amber' => 'bg-amber-500',
        'violet' => 'bg-violet-500',
        'primary' => 'bg-primary-600',
        default => 'bg-slate-500',
    };

    $textColor = match ($color) {
        'blue' => 'text-blue-700',
        'amber' => 'text-amber-700',
        'violet' => 'text-violet-700',
        'primary' => 'text-primary-700',
        default => 'text-slate-700',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-100 bg-slate-50/50 p-3']) }}>
    <div class="mb-2 flex items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</span>
        <span @class([
            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none',
            'bg-emerald-100 text-emerald-700' => $passes,
            'bg-red-100 text-red-700' => ! $passes && $score !== null,
            'bg-slate-100 text-slate-500' => $score === null,
        ])>
            @if ($score === null)
                —
            @elseif ($passes)
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Lulus
            @else
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                −{{ $threshold - $score }}
            @endif
        </span>
    </div>

    <div class="flex items-baseline gap-1.5">
        <span class="text-2xl font-bold tabular-nums {{ $textColor }}">{{ $score !== null ? $score : '—' }}</span>
        <span class="text-sm text-slate-400">/ {{ $threshold }}</span>
    </div>

    <div class="relative mt-3 h-2 overflow-hidden rounded-full bg-slate-200/80">
        <div class="{{ $barColor }} h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
        <div
            class="absolute top-0 bottom-0 w-0.5 bg-slate-500/70"
            style="left: {{ $thresholdPct }}%"
            title="Ambang batas {{ $threshold }}"
        ></div>
    </div>

    <p class="mt-2 text-[11px] text-slate-500">
        Ambang batas <span class="font-semibold text-slate-600">{{ $threshold }}</span>
    </p>
</div>
