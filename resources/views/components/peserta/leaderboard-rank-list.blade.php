@props([
    'entries',
    'currentUser' => null,
    'metric' => 'score',
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => 'Data leaderboard akan muncul setelah ada aktivitas.',
])

@php
    $metricThemes = [
        'score' => [
            'first' => 'from-amber-50/90 via-yellow-50/50 to-transparent ring-amber-200/60',
            'second' => 'from-slate-100/80 via-slate-50/40 to-transparent ring-slate-200/60',
            'third' => 'from-orange-50/80 via-amber-50/30 to-transparent ring-orange-200/50',
            'rank_first' => 'from-amber-300 to-amber-500 shadow-amber-200/60',
            'rank_second' => 'from-slate-300 to-slate-400 shadow-slate-200/60',
            'rank_third' => 'from-orange-300 to-orange-500 shadow-orange-200/60',
            'value_first' => 'bg-amber-100 text-amber-800 ring-amber-200/80',
            'value_second' => 'bg-slate-200/80 text-slate-700 ring-slate-300/60',
            'value_third' => 'bg-orange-100 text-orange-800 ring-orange-200/80',
            'value_default' => 'bg-slate-100 text-slate-700 ring-slate-200/80',
        ],
        'duel' => [
            'first' => 'from-rose-50/90 via-orange-50/50 to-transparent ring-rose-200/60',
            'second' => 'from-slate-100/80 via-slate-50/40 to-transparent ring-slate-200/60',
            'third' => 'from-orange-50/80 via-rose-50/30 to-transparent ring-orange-200/50',
            'rank_first' => 'from-rose-400 to-orange-500 shadow-rose-200/60',
            'rank_second' => 'from-slate-300 to-slate-400 shadow-slate-200/60',
            'rank_third' => 'from-orange-300 to-orange-500 shadow-orange-200/60',
            'value_first' => 'bg-rose-100 text-rose-800 ring-rose-200/80',
            'value_second' => 'bg-slate-200/80 text-slate-700 ring-slate-300/60',
            'value_third' => 'bg-orange-100 text-orange-800 ring-orange-200/80',
            'value_default' => 'bg-slate-100 text-slate-700 ring-slate-200/80',
        ],
        'xp' => [
            'first' => 'from-violet-50/90 via-purple-50/50 to-transparent ring-violet-200/60',
            'second' => 'from-slate-100/80 via-slate-50/40 to-transparent ring-slate-200/60',
            'third' => 'from-indigo-50/80 via-violet-50/30 to-transparent ring-indigo-200/50',
            'rank_first' => 'from-violet-400 to-purple-500 shadow-violet-200/60',
            'rank_second' => 'from-slate-300 to-slate-400 shadow-slate-200/60',
            'rank_third' => 'from-indigo-300 to-violet-500 shadow-indigo-200/60',
            'value_first' => 'bg-violet-100 text-violet-800 ring-violet-200/80',
            'value_second' => 'bg-slate-200/80 text-slate-700 ring-slate-300/60',
            'value_third' => 'bg-indigo-100 text-indigo-800 ring-indigo-200/80',
            'value_default' => 'bg-slate-100 text-slate-700 ring-slate-200/80',
        ],
    ];

    $theme = $metricThemes[$metric] ?? $metricThemes['score'];

    $formatValue = function (array $entry) use ($metric): string {
        return match ($metric) {
            'score' => format_exam_score($entry['score']),
            'duel' => $entry['wins'].'W',
            'xp' => number_format($entry['xp']).' XP',
            default => (string) ($entry['score'] ?? ''),
        };
    };
@endphp

<div {{ $attributes->class(['flex-1 overflow-y-auto']) }}>
    @if ($entries->isEmpty())
        <div class="flex flex-col items-center justify-center px-5 py-12 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-100 to-slate-50 text-slate-400 shadow-inner">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.367 2.684 6.003 6.003 0 01-5.367-2.684"/>
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-slate-700">{{ $emptyTitle }}</p>
            <p class="mt-1 max-w-xs text-xs leading-relaxed text-slate-400">{{ $emptyMessage }}</p>
        </div>
    @else
        <ol class="p-2">
            @foreach ($entries as $entry)
                <li wire:key="lb-{{ $metric }}-{{ $entry['user_id'] }}"
                    @class([
                        'group mb-1 flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition-all duration-500 ease-out last:mb-0',
                        'bg-gradient-to-r '.$theme['first'].' ring-1 shadow-sm' => $entry['rank'] === 1 && ! $entry['is_current'],
                        'bg-gradient-to-r '.$theme['second'].' ring-1' => $entry['rank'] === 2 && ! $entry['is_current'],
                        'bg-gradient-to-r '.$theme['third'].' ring-1' => $entry['rank'] === 3 && ! $entry['is_current'],
                        'hover:bg-slate-50/80' => $entry['rank'] > 3 && ! $entry['is_current'],
                        'bg-gradient-to-r from-primary-100/90 via-primary-50/60 to-indigo-50/40 ring-2 ring-primary-300/70 shadow-sm shadow-primary-100/50' => $entry['is_current'],
                    ])>

                    <span @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold shadow-sm',
                        'bg-gradient-to-br '.$theme['rank_first'].' text-white' => $entry['rank'] === 1,
                        'bg-gradient-to-br '.$theme['rank_second'].' text-white' => $entry['rank'] === 2,
                        'bg-gradient-to-br '.$theme['rank_third'].' text-white' => $entry['rank'] === 3,
                        'bg-slate-100 text-slate-500 ring-1 ring-slate-200/80' => $entry['rank'] > 3,
                    ])>
                        @if ($entry['rank'] === 1)
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/></svg>
                        @else
                            {{ $entry['rank'] }}
                        @endif
                    </span>

                    <span @class([
                        'min-w-0 flex-1 flex items-center gap-1 text-[13px] leading-tight',
                        'font-bold text-primary-900' => $entry['is_current'],
                        'font-semibold text-slate-800' => $entry['rank'] <= 3 && ! $entry['is_current'],
                        'font-medium text-slate-700' => $entry['rank'] > 3 && ! $entry['is_current'],
                    ])>
                        <span class="truncate" title="{{ $entry['name'] }}">{{ $entry['name'] }}</span>
                        @if ($entry['is_current'])
                            <span class="shrink-0 rounded-md bg-primary-200/60 px-1 py-px text-[9px] font-bold uppercase tracking-wide text-primary-800">Anda</span>
                        @endif
                    </span>

                    <span @class([
                        'shrink-0 rounded-lg px-2 py-0.5 text-xs font-extrabold tabular-nums',
                        'bg-primary-600 text-white shadow-sm shadow-primary-300/40' => $entry['is_current'],
                        $theme['value_first'].' ring-1' => $entry['rank'] === 1 && ! $entry['is_current'],
                        $theme['value_second'].' ring-1' => $entry['rank'] === 2 && ! $entry['is_current'],
                        $theme['value_third'].' ring-1' => $entry['rank'] === 3 && ! $entry['is_current'],
                        $theme['value_default'].' ring-1' => $entry['rank'] > 3 && ! $entry['is_current'],
                    ])>{{ $formatValue($entry) }}</span>
                </li>
            @endforeach
        </ol>

        @if ($currentUser)
            <div class="mx-2 mb-2 mt-1">
                <div class="flex items-center gap-2 px-1 py-1.5">
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-300 to-transparent"></div>
                    <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Posisi Anda</span>
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-300 to-transparent"></div>
                </div>

                <div class="flex items-center gap-2.5 rounded-xl bg-gradient-to-r from-primary-100/90 via-primary-50/60 to-indigo-50/40 px-2.5 py-2 ring-2 ring-primary-300/70 shadow-sm shadow-primary-100/50">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-xs font-extrabold text-white shadow-sm shadow-primary-300/40">
                        {{ $currentUser['rank'] }}
                    </span>
                    <span class="min-w-0 flex-1 flex items-center gap-1 text-[13px] font-bold leading-tight text-primary-900">
                        <span class="truncate" title="{{ $currentUser['name'] }}">{{ $currentUser['name'] }}</span>
                        <span class="shrink-0 rounded-md bg-primary-200/60 px-1 py-px text-[9px] font-bold uppercase tracking-wide text-primary-800">Anda</span>
                    </span>
                    <span class="shrink-0 rounded-lg bg-primary-600 px-2 py-0.5 text-xs font-extrabold tabular-nums text-white shadow-sm shadow-primary-300/40">
                        {{ $formatValue($currentUser) }}
                    </span>
                </div>
            </div>
        @endif
    @endif
</div>
