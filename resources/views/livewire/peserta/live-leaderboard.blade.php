<div class="ui-card relative flex h-full flex-col overflow-hidden border-amber-200/60 bg-gradient-to-b from-white via-amber-50/20 to-primary-50/30 shadow-lg shadow-amber-100/30"
     wire:poll.15s.visible>

    {{-- Header --}}
    <div class="relative overflow-hidden border-b border-amber-100/80 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-4 py-3.5">
        <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-6 left-1/3 h-14 w-14 rounded-full bg-indigo-400/20"></div>

        <div class="relative flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold tracking-tight text-white">Top 10 Live</h3>
                    <p class="text-[10px] font-medium text-primary-100/90">Leaderboard Skor Total</p>
                </div>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white ring-1 ring-white/20 backdrop-blur-sm">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-300 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-400"></span>
                </span>
                Live
            </span>
        </div>
    </div>

    {{-- Body --}}
    <div class="flex-1 overflow-y-auto">
        @if ($entries->isEmpty())
            <div class="flex flex-col items-center justify-center px-5 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-amber-50 text-amber-500 shadow-inner">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.003 6.003 0 01-5.367 2.684 6.003 6.003 0 01-5.367-2.684"/>
                    </svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-700">Belum ada skor</p>
                <p class="mt-1 max-w-[180px] text-xs leading-relaxed text-slate-400">Selesaikan simulasi untuk masuk leaderboard.</p>
            </div>
        @else
            <ol class="p-2">
                @foreach ($entries as $entry)
                    <li wire:key="lb-{{ $entry['user_id'] }}"
                        @class([
                            'group mb-1 flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition-all duration-500 ease-out last:mb-0',
                            'bg-gradient-to-r from-amber-50/90 via-yellow-50/50 to-transparent ring-1 ring-amber-200/60 shadow-sm' => $entry['rank'] === 1 && ! $entry['is_current'],
                            'bg-gradient-to-r from-slate-100/80 via-slate-50/40 to-transparent ring-1 ring-slate-200/60' => $entry['rank'] === 2 && ! $entry['is_current'],
                            'bg-gradient-to-r from-orange-50/80 via-amber-50/30 to-transparent ring-1 ring-orange-200/50' => $entry['rank'] === 3 && ! $entry['is_current'],
                            'hover:bg-slate-50/80' => $entry['rank'] > 3 && ! $entry['is_current'],
                            'bg-gradient-to-r from-primary-100/90 via-primary-50/60 to-indigo-50/40 ring-2 ring-primary-300/70 shadow-sm shadow-primary-100/50' => $entry['is_current'],
                        ])>

                        {{-- Rank badge --}}
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-extrabold shadow-sm',
                            'bg-gradient-to-br from-amber-300 to-amber-500 text-white shadow-amber-200/60' => $entry['rank'] === 1,
                            'bg-gradient-to-br from-slate-300 to-slate-400 text-white shadow-slate-200/60' => $entry['rank'] === 2,
                            'bg-gradient-to-br from-orange-300 to-orange-500 text-white shadow-orange-200/60' => $entry['rank'] === 3,
                            'bg-slate-100 text-slate-500 ring-1 ring-slate-200/80' => $entry['rank'] > 3,
                        ])>
                            @if ($entry['rank'] === 1)
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/></svg>
                            @elseif ($entry['rank'] === 2)
                                <span>2</span>
                            @elseif ($entry['rank'] === 3)
                                <span>3</span>
                            @else
                                {{ $entry['rank'] }}
                            @endif
                        </span>

                        {{-- Name --}}
                        <span @class([
                            'min-w-0 flex-1 flex flex-wrap items-center gap-1 text-[13px] leading-tight',
                            'font-bold text-primary-900' => $entry['is_current'],
                            'font-semibold text-slate-800' => $entry['rank'] <= 3 && ! $entry['is_current'],
                            'font-medium text-slate-700' => $entry['rank'] > 3 && ! $entry['is_current'],
                        ])>
                            <span class="truncate" title="{{ $entry['name'] }}">{{ $entry['name'] }}</span>
                            <x-devotion-badge :badge="$entry['devotion_badge'] ?? null" />
                            @if ($entry['is_current'])
                                <span class="shrink-0 rounded-md bg-primary-200/60 px-1 py-px text-[9px] font-bold uppercase tracking-wide text-primary-800">Anda</span>
                            @endif
                        </span>

                        {{-- Score --}}
                        <span @class([
                            'shrink-0 rounded-lg px-2 py-0.5 text-xs font-extrabold tabular-nums',
                            'bg-primary-600 text-white shadow-sm shadow-primary-300/40' => $entry['is_current'],
                            'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80' => $entry['rank'] === 1 && ! $entry['is_current'],
                            'bg-slate-200/80 text-slate-700 ring-1 ring-slate-300/60' => $entry['rank'] === 2 && ! $entry['is_current'],
                            'bg-orange-100 text-orange-800 ring-1 ring-orange-200/80' => $entry['rank'] === 3 && ! $entry['is_current'],
                            'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80' => $entry['rank'] > 3 && ! $entry['is_current'],
                        ])>{{ format_exam_score($entry['score']) }}</span>
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
                        <span class="min-w-0 flex-1 flex flex-wrap items-center gap-1 text-[13px] font-bold leading-tight text-primary-900">
                            <span class="truncate" title="{{ $currentUser['name'] }}">{{ $currentUser['name'] }}</span>
                            <x-devotion-badge :badge="$currentUser['devotion_badge'] ?? null" />
                            <span class="shrink-0 rounded-md bg-primary-200/60 px-1 py-px text-[9px] font-bold uppercase tracking-wide text-primary-800">Anda</span>
                        </span>
                        <span class="shrink-0 rounded-lg bg-primary-600 px-2 py-0.5 text-xs font-extrabold tabular-nums text-white shadow-sm shadow-primary-300/40">
                            {{ format_exam_score($currentUser['score']) }}
                        </span>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
