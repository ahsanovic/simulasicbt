@props([
    'label',
    'value',
    'iconBg' => 'bg-slate-100',
    'iconColor' => 'text-slate-600',
    'valueClass' => 'text-2xl font-bold tabular-nums text-slate-900',
])

<div {{ $attributes->merge(['class' => 'ui-card p-5']) }}>
    <div class="flex items-start gap-3">
        <div @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', $iconBg, $iconColor])>
            {{ $icon }}
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
            <p @class(['mt-1', $valueClass])>{{ $value }}</p>
            @if (! $slot->isEmpty())
                <div class="mt-1 space-y-0.5 text-xs text-slate-500">{{ $slot }}</div>
            @endif
        </div>
    </div>
</div>
