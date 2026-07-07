@props([
    'rating' => 0,
])

@php
    $labels = [
        1 => 'Kurang memuaskan',
        2 => 'Cukup',
        3 => 'Baik',
        4 => 'Sangat baik',
        5 => 'Luar biasa!',
    ];
@endphp

<div {{ $attributes }}>
    <p class="mb-2 text-sm font-semibold text-slate-700">Rating Pengalaman <span class="text-rose-500">*</span></p>
    <div class="flex flex-wrap items-center gap-2">
        @for ($star = 1; $star <= 5; $star++)
            <button
                type="button"
                wire:click="$set('rating', {{ $star }})"
                @class([
                    'rounded-lg p-1.5 transition',
                    'bg-rose-50 ring-2 ring-rose-300' => (int) $rating === $star,
                    'hover:bg-rose-50/70' => (int) $rating !== $star,
                ])
                aria-label="Beri rating {{ $star }} bintang"
            >
                <svg @class([
                    'h-8 w-8',
                    'text-amber-400' => $star <= (int) $rating,
                    'text-slate-300' => $star > (int) $rating,
                ]) fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </button>
        @endfor
        @if ((int) $rating > 0)
            <span class="text-sm font-medium text-rose-700">{{ $labels[(int) $rating] ?? '' }}</span>
        @endif
    </div>
    @error('rating') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
