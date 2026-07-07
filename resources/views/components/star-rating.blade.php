@props([
    'rating' => 0,
    'size' => 'md',
    'showValue' => false,
])

@php
    $rating = max(0, min(5, (int) $rating));
    $sizeClass = match ($size) {
        'sm' => 'h-3.5 w-3.5',
        'lg' => 'h-6 w-6',
        default => 'h-4 w-4',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }}>
    @for ($star = 1; $star <= 5; $star++)
        <svg @class([$sizeClass, $star <= $rating ? 'text-amber-400' : 'text-slate-200']) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>
    @endfor
    @if ($showValue && $rating > 0)
        <span class="ml-1 text-sm font-semibold text-slate-700">{{ $rating }}/5</span>
    @endif
</div>
