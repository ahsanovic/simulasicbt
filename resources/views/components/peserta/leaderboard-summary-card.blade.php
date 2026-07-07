@props([
    'ranks' => ['score' => null, 'duel' => null, 'xp' => null],
])

@php
    $formatRank = fn (?int $rank): string => $rank !== null ? '#'.$rank : '—';
@endphp

<div class="ui-card relative overflow-hidden border-primary-200/60 bg-gradient-to-b from-white via-primary-50/20 to-indigo-50/30 shadow-lg shadow-primary-100/30">
    <div class="relative overflow-hidden border-b border-primary-100/80 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-4 py-3.5">
        <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>

        <div class="relative flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold tracking-tight text-white">Papan Peringkat</h3>
                    <p class="text-[10px] font-medium text-primary-100/90">Posisi Anda saat ini</p>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-3 p-4">
        <div class="grid grid-cols-3 gap-2">
            <a href="{{ route('peserta.leaderboard.index', ['tab' => 'score']) }}"
               wire:navigate
               class="rounded-xl bg-amber-50/80 px-2 py-2.5 text-center ring-1 ring-amber-100 transition hover:bg-amber-100/80">
                <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700/80">Skor</p>
                <p class="mt-0.5 text-sm font-extrabold tabular-nums text-amber-900">{{ $formatRank($ranks['score']) }}</p>
            </a>
            <a href="{{ route('peserta.leaderboard.index', ['tab' => 'duel']) }}"
               wire:navigate
               class="rounded-xl bg-rose-50/80 px-2 py-2.5 text-center ring-1 ring-rose-100 transition hover:bg-rose-100/80">
                <p class="text-[10px] font-bold uppercase tracking-wide text-rose-700/80">Duel</p>
                <p class="mt-0.5 text-sm font-extrabold tabular-nums text-rose-900">{{ $formatRank($ranks['duel']) }}</p>
            </a>
            <a href="{{ route('peserta.leaderboard.index', ['tab' => 'xp']) }}"
               wire:navigate
               class="rounded-xl bg-violet-50/80 px-2 py-2.5 text-center ring-1 ring-violet-100 transition hover:bg-violet-100/80">
                <p class="text-[10px] font-bold uppercase tracking-wide text-violet-700/80">XP</p>
                <p class="mt-0.5 text-sm font-extrabold tabular-nums text-violet-900">{{ $formatRank($ranks['xp']) }}</p>
            </a>
        </div>

        <a href="{{ route('peserta.leaderboard.index') }}"
           wire:navigate
           class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary-300/40 transition hover:bg-primary-700">
            Lihat Semua
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
</div>
