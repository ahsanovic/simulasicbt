@props(['label', 'value', 'icon' => null, 'trend' => null, 'color' => 'primary'])

@php
    $iconBg = match($color) {
        'primary' => 'bg-primary-100 text-primary-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'amber' => 'bg-amber-100 text-amber-600',
        'violet' => 'bg-violet-100 text-violet-600',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'ui-card p-5 transition hover:shadow-md hover:shadow-slate-200/60']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $value }}</p>
            @if ($trend)
                <p class="mt-2 text-xs font-medium text-slate-400">{{ $trend }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $iconBg }}">
                <x-ui.icon :name="$icon" />
            </div>
        @endif
    </div>
</div>
