<div class="ui-card relative flex h-full flex-col overflow-hidden border-violet-200/60 bg-gradient-to-b from-white via-violet-50/20 to-indigo-50/30 shadow-lg shadow-violet-100/30"
     wire:poll.15s.visible>

    <div class="relative overflow-hidden border-b border-violet-100/80 bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 px-4 py-3.5">
        <div class="pointer-events-none absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>

        <div class="relative flex items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold tracking-tight text-white">Top 10 XP</h3>
                    <p class="text-[10px] font-medium text-violet-100/90">Hall of Fame Total XP</p>
                </div>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white ring-1 ring-white/20 backdrop-blur-sm">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-300 opacity-75"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-violet-400"></span>
                </span>
                Live
            </span>
        </div>
    </div>

    <x-peserta.leaderboard-rank-list
        :entries="$entries"
        :current-user="$currentUser"
        metric="xp"
        empty-title="Belum ada XP"
        empty-message="Kerjakan aktivitas belajar untuk mulai mengumpulkan XP."
    />

    <div class="border-t border-violet-100/80 bg-violet-50/40 px-4 py-3">
        <x-peserta.xp-earn-guide variant="compact" />
    </div>
</div>
