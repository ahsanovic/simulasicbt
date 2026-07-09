@props([
    'streakInfo',
    'variant' => 'compact',
])

@php
    $streak = (int) ($streakInfo['streak'] ?? 0);
    $multiplierLabel = $streakInfo['multiplier_label'] ?? '1x';
    $nextTierAt = $streakInfo['next_tier_at'] ?? null;
    $nextMultiplierLabel = $streakInfo['next_multiplier_label'] ?? null;
@endphp

@if ($variant === 'compact')
    <div {{ $attributes->class(['rounded-xl px-4 py-3 text-center text-sm font-semibold', 'bg-orange-50 text-orange-900' => $streak > 0, 'bg-slate-50 text-slate-600' => $streak === 0]) }}>
        @if ($streak > 0)
            🔥 {{ $streak }} hari streak konsistensi · pengali XP <span class="text-orange-700">{{ $multiplierLabel }}</span> aktif hari ini
            @if ($nextTierAt)
                <span class="mt-1 block text-xs font-medium text-orange-700/80">
                    {{ max(0, $nextTierAt - $streak) }} hari lagi menuju pengali {{ $nextMultiplierLabel }}
                </span>
            @endif
        @else
            Mulai streak harian dengan 1 aktivitas belajar — Audio Mode, Kartu Sakti, Duel, atau baca materi.
        @endif
    </div>
@else
    <div {{ $attributes->class(['rounded-xl border px-3 py-3', 'border-orange-200 bg-gradient-to-r from-orange-50 to-amber-50' => $streak > 0, 'border-slate-200 bg-slate-50/80' => $streak === 0]) }}>
        <div class="flex items-start gap-3">
            <div @class([
                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                'bg-orange-100 text-orange-600' => $streak > 0,
                'bg-slate-200 text-slate-500' => $streak === 0,
            ])>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-700/80">Streak Konsistensi</p>
                @if ($streak > 0)
                    <p class="mt-1 text-sm font-bold text-slate-900">
                        {{ $streak }} hari berturut-turut · pengali XP <span class="text-orange-700">{{ $multiplierLabel }}</span>
                    </p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-600">
                        XP dari Audio Mode, Kartu Sakti, dan Duel dikalikan hari ini.
                        @if ($nextTierAt)
                            {{ max(0, $nextTierAt - $streak) }} hari lagi naik ke pengali {{ $nextMultiplierLabel }}.
                        @else
                            Pengali maksimal tercapai — pertahankan streak setiap hari!
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-sm font-bold text-slate-900">Belum ada streak aktif</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-600">
                        Selesaikan minimal 1 aktivitas per hari agar XP Anda terus bertambah lebih cepat.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif
