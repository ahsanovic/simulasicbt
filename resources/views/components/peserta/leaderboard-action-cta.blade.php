@props([
    'metric' => 'score',
    'currentUser' => null,
])

@php
    $config = match ($metric) {
        'duel' => [
            'label' => 'Main Duel',
            'route' => route('peserta.duel.index'),
            'accent' => 'text-rose-600',
            'button' => 'bg-rose-600 text-white shadow-sm shadow-rose-200/60 hover:bg-rose-700',
            'ring' => 'ring-rose-200',
            'bg' => 'bg-rose-50/40',
            'border' => 'border-rose-100/80',
            'default_message' => 'Naik peringkat duel — tantang lawan sekarang.',
            'rank_message' => fn (int $rank) => "Kamu di peringkat #{$rank} — menangkan duel lagi untuk naik!",
        ],
        default => [
            'label' => 'Mulai Simulasi',
            'route' => route('peserta.dashboard'),
            'accent' => 'text-amber-600',
            'button' => 'bg-amber-600 text-white shadow-sm shadow-amber-200/60 hover:bg-amber-700',
            'ring' => 'ring-amber-200',
            'bg' => 'bg-amber-50/40',
            'border' => 'border-amber-100/80',
            'default_message' => 'Tantang skor terbaikmu — mulai simulasi sekarang.',
            'rank_message' => fn (int $rank) => "Kamu di peringkat #{$rank} — satu simulasi lagi bisa naik!",
        ],
    };

    $message = $currentUser
        ? $config['rank_message']((int) $currentUser['rank'])
        : $config['default_message'];
@endphp

<div {{ $attributes->class(['border-t px-4 py-3 sm:px-6', $config['border'], $config['bg']]) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs leading-relaxed text-slate-600">
            <span class="font-semibold {{ $config['accent'] }}">{{ $message }}</span>
        </p>
        <a href="{{ $config['route'] }}"
           wire:navigate
           class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition {{ $config['button'] }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                @if ($metric === 'duel')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                @endif
            </svg>
            {{ $config['label'] }}
        </a>
    </div>
</div>
