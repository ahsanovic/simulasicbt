@props([
    'progress',
    'streakInfo' => null,
    'variant' => 'full',
])

@if ($variant === 'compact')
    <div class="ui-card relative flex h-full flex-col overflow-hidden border-emerald-200/60 bg-gradient-to-b from-white via-emerald-50/20 to-indigo-50/20 shadow-lg shadow-emerald-100/20">
        <div class="relative overflow-hidden border-b border-emerald-100/80 bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 px-4 py-3">
            <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>

            <div class="relative flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold tracking-tight text-white">Lencana Pengabdian</h3>
                    <p class="text-[10px] font-medium text-emerald-100/90">{{ number_format($progress['xp']) }} XP · {{ $progress['current_badge']['label'] }}</p>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <div class="flex flex-1 flex-col justify-center">
                <div class="flex flex-wrap items-center gap-2">
                    <x-devotion-badge :badge="$progress['current_badge']" size="md" />
                    @if ($streakInfo && ($streakInfo['streak'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1 rounded-lg bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700 ring-1 ring-orange-100">
                            🔥 {{ $streakInfo['streak'] }} hari · {{ $streakInfo['multiplier_label'] }}
                        </span>
                    @endif
                </div>

                @if ($progress['is_max_tier'])
                    <p class="mt-3 text-xs font-semibold text-amber-800">Kasta tertinggi tercapai!</p>
                @else
                    <div class="mt-3">
                        <div class="mb-1 flex items-center justify-between gap-2 text-[10px]">
                            <span class="font-semibold text-slate-600">Menuju {{ $progress['next_badge']['label'] }}</span>
                            <span class="shrink-0 font-bold tabular-nums text-indigo-600">{{ $progress['progress_percent'] }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/80">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-500 transition-all duration-500"
                                 style="width: {{ $progress['progress_percent'] }}%"></div>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-500">
                            Butuh <span class="font-bold text-slate-700">{{ number_format($progress['xp_to_next']) }} XP</span> lagi
                        </p>
                    </div>
                @endif
            </div>

            <a href="#devotion-badge-card"
               class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-300/40 transition hover:bg-emerald-700">
                Cara naik pangkat
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </div>
    </div>
@else
    <div id="devotion-badge-card" class="ui-card relative scroll-mt-24 overflow-hidden border-emerald-200/60 bg-gradient-to-b from-white via-emerald-50/20 to-indigo-50/20 shadow-lg shadow-emerald-100/20">
        <div class="relative overflow-hidden border-b border-emerald-100/80 bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 px-4 py-3.5">
            <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>

            <div class="relative flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold tracking-tight text-white">Lencana Pengabdian</h3>
                    <p class="text-[10px] font-medium text-emerald-100/90">Pangkat virtual berdasarkan XP</p>
                </div>
            </div>
        </div>

        <div class="space-y-4 p-4">
            <div class="rounded-xl bg-white/80 p-3 ring-1 ring-slate-200/80">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pangkat Anda</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-devotion-badge :badge="$progress['current_badge']" size="md" />
                    <span class="text-xs font-semibold tabular-nums text-slate-500">{{ number_format($progress['xp']) }} XP</span>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $progress['current_badge']['description'] }}</p>
            </div>

            @if ($streakInfo)
                <x-peserta.daily-streak-panel :streak-info="$streakInfo" variant="full" />
            @endif

            @if ($progress['is_max_tier'])
                <div class="rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-3 py-2.5">
                    <p class="text-xs font-bold text-amber-800">Kasta tertinggi tercapai!</p>
                    <p class="mt-0.5 text-[11px] leading-relaxed text-amber-700/90">Anda sudah menjadi Teladan Loyal — inspirasi bagi pejuang CPNS lainnya.</p>
                </div>
            @else
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-2 text-[11px]">
                        <span class="font-semibold text-slate-600">Menuju {{ $progress['next_badge']['label'] }}</span>
                        <span class="shrink-0 font-bold tabular-nums text-indigo-600">{{ $progress['progress_percent'] }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/80">
                        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-500 transition-all duration-500"
                             style="width: {{ $progress['progress_percent'] }}%"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        Butuh <span class="font-bold text-slate-700">{{ number_format($progress['xp_to_next']) }} XP</span> lagi untuk naik pangkat.
                    </p>
                </div>
            @endif

            <div>
                <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">Tingkatan Pangkat</p>
                <ol class="space-y-1.5">
                    @foreach ($progress['tiers'] as $tier)
                        <li @class([
                            'flex items-start gap-2 rounded-lg px-2 py-1.5 text-[11px] leading-snug',
                            'bg-emerald-50/80 ring-1 ring-emerald-200/60' => $tier['is_current'],
                            'bg-slate-50/70 ring-1 ring-slate-200/50' => ! $tier['is_unlocked'] && ! $tier['is_current'],
                        ])>
                            <span @class([
                                'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[9px] font-bold',
                                'bg-emerald-500 text-white' => $tier['is_unlocked'],
                                'bg-slate-300 text-slate-600' => ! $tier['is_unlocked'],
                            ])>
                                @if ($tier['is_unlocked'])
                                    ✓
                                @else
                                    ·
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @php
                                        $tierBadge = $tier;
                                        if (! $tier['is_unlocked']) {
                                            $tierBadge['classes'] = 'text-slate-500 bg-slate-100 ring-slate-200/60 opacity-60';
                                        }
                                    @endphp
                                    <x-devotion-badge :badge="$tierBadge" />
                                    <span @class([
                                        'text-[10px] font-semibold tabular-nums',
                                        'text-emerald-700' => $tier['is_current'],
                                        'text-indigo-600' => $tier['is_unlocked'] && ! $tier['is_current'],
                                        'text-slate-500' => ! $tier['is_unlocked'],
                                    ])>{{ number_format($tier['min_xp']) }}+ XP</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <x-peserta.xp-earn-guide variant="list" />

            <p class="text-center text-[10px] leading-relaxed text-slate-400">
                Lencana tampil di samping nama Anda di papan peringkat &amp; testimoni.
            </p>
        </div>
    </div>
@endif
