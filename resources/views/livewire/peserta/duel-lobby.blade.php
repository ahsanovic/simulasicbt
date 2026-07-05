<div class="min-h-screen bg-gradient-to-b from-slate-50 to-rose-50/30">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-rose-600 via-red-600 to-orange-600 p-6 text-white shadow-xl shadow-rose-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-rose-200">Duel 1v1</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Challenge a Friend</h1>
                    <p class="mt-2 max-w-xl text-sm text-rose-100">
                        Mini-tryout 15 soal (TWK, TIU, TKP) dalam 10 menit. Skor tertinggi menang — jika seri, yang selesai lebih cepat menang!
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                    <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Real-time Duel
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(240px,28%)] lg:items-start">
            <div class="space-y-6">
                @if ($mode === 'matchmaking' && $waitingSession)
                    <div class="ui-card p-6 text-center" wire:poll.2s="checkMatchmaking">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                            <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </div>
                        <h2 class="mt-4 text-lg font-bold text-slate-900">Mencari Lawan...</h2>
                        <p class="mt-2 text-sm text-slate-500">Menunggu peserta lain yang juga mengklik matchmaking.</p>
                        <p class="mt-3 text-xs leading-relaxed text-slate-400">
                            Duel dimulai saat ada lawan di antrean. Jika tidak ada dalam {{ \App\Services\DuelService::MATCHMAKING_BOT_WAIT_SECONDS }} detik, Anda akan melawan AI Shadow Bot.
                        </p>
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            <button type="button" wire:click="cancelMatchmaking" class="ui-btn-secondary">Batalkan</button>
                        </div>
                    </div>
                @elseif ($mode === 'challenge_pending' && $waitingSession)
                    <div class="ui-card p-6 text-center" wire:poll.2s="checkChallengeResponse">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600">
                            <svg class="h-8 w-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <h2 class="mt-4 text-lg font-bold text-slate-900">Menunggu Respons Lawan</h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Tantangan dikirim ke <strong>{{ $waitingSession->opponent?->name }}</strong>.
                        </p>
                        <p class="mt-3 text-xs leading-relaxed text-slate-400">
                            Duel akan dimulai setelah lawan menerima tantangan. Anda akan mendapat notifikasi jika lawan menerima atau menolak.
                        </p>
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            <button type="button" wire:click="cancelChallengePending" class="ui-btn-secondary">Batalkan Tantangan</button>
                        </div>
                    </div>
                @elseif ($mode === 'waiting' && $waitingSession)
                    <div class="ui-card p-6 text-center" wire:poll.3s="checkWaitingRoom">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                            <svg class="h-8 w-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h2 class="mt-4 text-lg font-bold text-slate-900">Menunggu Lawan Bergabung</h2>
                        <p class="mt-2 text-sm text-slate-500">Bagikan kode duel ini ke teman Anda:</p>
                        <p class="mt-3 inline-block rounded-xl bg-slate-900 px-6 py-3 font-mono text-2xl font-bold tracking-widest text-white">{{ $waitingSession->code }}</p>
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            <button type="button" wire:click="cancelWaiting" class="ui-btn-secondary">Batal</button>
                        </div>
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Random Matchmaking --}}
                        <button type="button"
                                wire:click="findRandomMatch"
                                wire:loading.attr="disabled"
                                class="group ui-card relative overflow-hidden p-5 text-left transition hover:-translate-y-0.5 hover:border-rose-200 hover:shadow-lg hover:shadow-rose-500/10">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-orange-500 text-white shadow-md">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <h3 class="mt-4 text-sm font-bold text-slate-900 group-hover:text-rose-700">Cari Lawan Acak</h3>
                            <p class="mt-1 text-xs text-slate-500">Masuk antrean matchmaking. Duel dimulai saat ada lawan lain yang juga mengantre, atau lawan AI jika tidak ada.</p>
                            <span wire:loading wire:target="findRandomMatch" class="mt-3 block text-xs font-semibold text-rose-600">Mencari lawan...</span>
                        </button>

                        {{-- Invite Code --}}
                        <button type="button"
                                wire:click="createInviteCode"
                                class="group ui-card relative overflow-hidden p-5 text-left transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-500/10">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            </div>
                            <h3 class="mt-4 text-sm font-bold text-slate-900 group-hover:text-indigo-700">Buat Kode Undangan</h3>
                            <p class="mt-1 text-xs text-slate-500">Buat ruang duel dan bagikan kode ke teman untuk bergabung.</p>
                        </button>
                    </div>

                    {{-- Challenge Friend --}}
                    <div class="ui-card p-5">
                        <h3 class="text-sm font-bold text-slate-900">Tantang Teman Langsung</h3>
                        <p class="mt-1 text-xs text-slate-500">Masukkan username, NIP, atau email peserta lain.</p>
                        <form wire:submit="challengeFriend" class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <input type="text"
                                   wire:model="friendIdentifier"
                                   placeholder="username / NIP / email"
                                   class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400/20">
                            <button type="submit" wire:loading.attr="disabled" class="ui-btn-success shrink-0 px-6">
                                <span wire:loading.remove wire:target="challengeFriend">Tantang →</span>
                                <span wire:loading wire:target="challengeFriend">Memulai...</span>
                            </button>
                        </form>
                        @error('friendIdentifier') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @error('identifier') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Join Code --}}
                    <div class="ui-card p-5">
                        <h3 class="text-sm font-bold text-slate-900">Gabung dengan Kode</h3>
                        <form wire:submit="joinByCode" class="mt-4 flex flex-col gap-3 sm:flex-row">
                            <input type="text"
                                   wire:model="joinCode"
                                   placeholder="Kode duel (6 karakter)"
                                   class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm uppercase focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/20">
                            <button type="submit" class="ui-btn-primary shrink-0 px-6">Gabung →</button>
                        </form>
                        @error('joinCode') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @error('code') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($recentDuels->isNotEmpty())
                    <div>
                        <h2 class="mb-3 text-sm font-bold text-slate-900">Duel Terakhir</h2>
                        <div class="space-y-2">
                            @foreach ($recentDuels as $duel)
                                @php
                                    $myAttempt = $duel->attemptFor(auth()->id());
                                    $opponentAttempt = auth()->id() === $duel->host_user_id
                                        ? $duel->opponentAttempt
                                        : $duel->hostAttempt;
                                    $isWinner = $duel->winner_user_id === auth()->id();
                                    $isDraw = $duel->winner_user_id === null;
                                @endphp
                                <a href="{{ route('peserta.duel.room', $duel) }}"
                                   wire:navigate
                                   wire:key="duel-history-{{ $duel->id }}"
                                   class="ui-card flex items-center justify-between gap-3 p-4 transition hover:border-rose-200 hover:shadow-md">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">vs {{ $duel->opponentLabelFor(auth()->id()) }}</p>
                                        <p class="text-xs text-slate-500">{{ $duel->created_at->diffForHumans() }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="text-xs tabular-nums text-slate-600">
                                            <x-exam-score :value="$myAttempt?->total_score ?? 0" />
                                            –
                                            <x-exam-score :value="$opponentAttempt?->total_score ?? 0" />
                                        </span>
                                        <span @class([
                                            'ui-badge text-[10px]',
                                            'bg-emerald-50 text-emerald-700' => $isWinner,
                                            'bg-slate-100 text-slate-600' => $isDraw,
                                            'bg-rose-50 text-rose-700' => ! $isWinner && ! $isDraw,
                                        ])>
                                            {{ $isDraw ? 'Seri' : ($isWinner ? 'Menang' : 'Kalah') }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="space-y-4 lg:sticky lg:top-6">
                <livewire:peserta.duel-leaderboard />
            </aside>
        </div>
    </main>

    @if ($enableNotificationPoll)
        <livewire:peserta.duel-notification-listener />
    @endif
</div>
