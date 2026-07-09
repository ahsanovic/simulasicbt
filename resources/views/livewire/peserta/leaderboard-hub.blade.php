<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mb-8 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-widest text-primary-200">Kompetisi & Motivasi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Papan Peringkat</h1>
                    <p class="mt-2 max-w-2xl text-sm text-primary-100 sm:text-base">
                        Lihat peringkat skor terbaik, kemenangan duel, dan Hall of Fame XP dari seluruh peserta.
                    </p>
                </div>
                <a href="{{ route('peserta.dashboard') }}"
                   wire:navigate
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Kembali ke Simulasi
                </a>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap gap-2 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200/80">
            <button type="button"
                    wire:click="setTab('score')"
                    @class([
                        'inline-flex flex-1 min-w-[7.5rem] items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-amber-50 text-amber-800 ring-1 ring-amber-200 shadow-sm' => $tab === 'score',
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'score',
                    ])>
                <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/></svg>
                Skor Terbaik
            </button>
            <button type="button"
                    wire:click="setTab('duel')"
                    @class([
                        'inline-flex flex-1 min-w-[7.5rem] items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-rose-50 text-rose-800 ring-1 ring-rose-200 shadow-sm' => $tab === 'duel',
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'duel',
                    ])>
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Duel
            </button>
            <button type="button"
                    wire:click="setTab('xp')"
                    @class([
                        'inline-flex flex-1 min-w-[7.5rem] items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-violet-50 text-violet-800 ring-1 ring-violet-200 shadow-sm' => $tab === 'xp',
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'xp',
                    ])>
                <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                Hall of Fame XP
            </button>
        </div>

        @if ($tab === 'score')
            <div class="ui-card relative flex min-h-[28rem] flex-col overflow-hidden border-amber-200/60 bg-gradient-to-b from-white via-amber-50/20 to-primary-50/30 shadow-lg shadow-amber-100/30"
                 wire:poll.15s.visible>
                <div class="relative overflow-hidden border-b border-amber-100/80 bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 px-4 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white">Top 50 Skor Terbaik</h2>
                            <p class="mt-0.5 text-xs text-primary-100">Skor total tertinggi dari seluruh simulasi yang diselesaikan</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white ring-1 ring-white/20">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-300 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-red-400"></span>
                            </span>
                            Live
                        </span>
                    </div>
                </div>

                <x-peserta.leaderboard-rank-list
                    :entries="$scoreData['entries']"
                    :current-user="$scoreData['current_user']"
                    metric="score"
                    empty-title="Belum ada skor"
                    empty-message="Selesaikan simulasi untuk masuk leaderboard skor terbaik."
                />
            </div>
        @elseif ($tab === 'duel')
            <div class="ui-card relative flex min-h-[28rem] flex-col overflow-hidden border-rose-200/60 bg-gradient-to-b from-white via-rose-50/20 to-orange-50/30 shadow-lg shadow-rose-100/30"
                 wire:poll.15s.visible>
                <div class="relative overflow-hidden border-b border-rose-100/80 bg-gradient-to-r from-rose-600 via-red-600 to-orange-600 px-4 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white">Top 50 Duel</h2>
                            <p class="mt-0.5 text-xs text-rose-100">Peringkat berdasarkan jumlah kemenangan duel 1v1</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white ring-1 ring-white/20">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-300 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            </span>
                            Live
                        </span>
                    </div>
                </div>

                <x-peserta.leaderboard-rank-list
                    :entries="$duelData['entries']"
                    :current-user="$duelData['current_user']"
                    metric="duel"
                    empty-title="Belum ada duel"
                    empty-message="Menangkan duel pertama untuk masuk leaderboard duel."
                />
            </div>
        @else
            <div class="ui-card relative flex min-h-[28rem] flex-col overflow-hidden border-violet-200/60 bg-gradient-to-b from-white via-violet-50/20 to-indigo-50/30 shadow-lg shadow-violet-100/30">
                <div class="relative overflow-hidden border-b border-violet-100/80 bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 px-4 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white">Hall of Fame — Total XP</h2>
                            <p class="mt-0.5 text-xs text-violet-100">Akumulasi XP dari simulasi, duel, Audio Mode, Kartu Sakti, dan aktivitas lainnya</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white ring-1 ring-white/20">
                            All Time
                        </span>
                    </div>
                </div>

                <x-peserta.leaderboard-rank-list
                    :entries="$xpData['entries']"
                    :current-user="$xpData['current_user']"
                    metric="xp"
                    empty-title="Belum ada XP"
                    empty-message="Kerjakan aktivitas belajar untuk mulai mengumpulkan XP."
                />

                <div class="border-t border-violet-100/80 bg-violet-50/40 px-4 py-3 sm:px-6">
                    <x-peserta.xp-earn-guide variant="compact" />
                </div>
            </div>
        @endif
    </main>
</div>
