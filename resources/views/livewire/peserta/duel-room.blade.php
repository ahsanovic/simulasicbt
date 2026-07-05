<div class="min-h-screen bg-slate-100"
     wire:key="duel-room-{{ $session->id }}"
     @if (! $showResult) wire:poll.5s="pollSession" @endif>

    @if ($waitingForOpponent && ! $showResult)
        <main class="mx-auto max-w-lg px-4 py-20 text-center">
            <div class="ui-card p-8" wire:poll.3s="pollSession">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600">
                    <svg class="h-8 w-8 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="mt-4 text-xl font-bold text-slate-900">Jawaban Terkunci</h2>
                <p class="mt-2 text-sm text-slate-500">Menunggu {{ $this->opponentLabel }} menyelesaikan duel...</p>
                <p class="mt-4 text-xs text-indigo-600">Progress lawan: {{ $this->opponentProgress }}/{{ $session::TOTAL_QUESTIONS }} soal</p>
            </div>
        </main>
    @elseif ($showResult)
        @php
            $myAttempt = $session->attemptFor(auth()->id());
            $opponentAttempt = auth()->id() === $session->host_user_id
                ? $session->opponentAttempt
                : $session->hostAttempt;
            $isWinner = $session->winner_user_id === auth()->id();
            $isDraw = $session->winner_user_id === null;
            $opponentLabel = $session->opponentLabelFor(auth()->id());
        @endphp

        <main class="mx-auto max-w-2xl px-4 py-12 sm:px-6">
            <div class="ui-card overflow-hidden">
                <div @class([
                    'px-6 py-8 text-center text-white',
                    'bg-gradient-to-r from-emerald-500 to-teal-600' => $isWinner,
                    'bg-gradient-to-r from-slate-500 to-slate-600' => $isDraw,
                    'bg-gradient-to-r from-rose-500 to-red-600' => ! $isWinner && ! $isDraw,
                ])>
                    <p class="text-xs font-bold uppercase tracking-widest opacity-80">Hasil Duel</p>
                    <h1 class="mt-2 text-3xl font-bold">
                        @if ($isDraw) Seri!
                        @elseif ($isWinner) Anda Menang!
                        @else Anda Kalah
                        @endif
                    </h1>
                    <p class="mt-2 text-sm opacity-90">vs {{ $opponentLabel }}</p>
                </div>

                <div class="grid grid-cols-2 divide-x divide-slate-100 border-b border-slate-100">
                    <div class="p-6 text-center">
                        <p class="text-xs font-semibold uppercase text-slate-500">Anda</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900"><x-exam-score :value="$myAttempt?->total_score ?? 0" /></p>
                        <div class="mt-3 flex justify-center gap-2 text-[10px] text-slate-500">
                            <span>TWK {{ $myAttempt?->score_twk ?? 0 }}</span>
                            <span>·</span>
                            <span>TIU {{ $myAttempt?->score_tiu ?? 0 }}</span>
                            <span>·</span>
                            <span>TKP {{ $myAttempt?->score_tkp ?? 0 }}</span>
                        </div>
                    </div>
                    <div class="p-6 text-center">
                        <p class="text-xs font-semibold uppercase text-slate-500">{{ $opponentLabel }}</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900"><x-exam-score :value="$opponentAttempt?->total_score ?? 0" /></p>
                        <div class="mt-3 flex justify-center gap-2 text-[10px] text-slate-500">
                            <span>TWK {{ $opponentAttempt?->score_twk ?? 0 }}</span>
                            <span>·</span>
                            <span>TIU {{ $opponentAttempt?->score_tiu ?? 0 }}</span>
                            <span>·</span>
                            <span>TKP {{ $opponentAttempt?->score_tkp ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-3 p-6">
                    <a href="{{ route('peserta.duel.index') }}" wire:navigate class="ui-btn-success">Duel Lagi</a>
                    <a href="{{ route('peserta.dashboard') }}" wire:navigate class="ui-btn-secondary">Dashboard</a>
                </div>
            </div>
        </main>
    @else
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-rose-600">Duel 1v1 · Mini-Tryout</p>
                    <h1 class="truncate text-lg font-bold text-slate-900">vs {{ $this->opponentLabel }}</h1>
                    <p class="text-sm text-slate-500">Soal <span class="font-semibold text-slate-800">{{ $currentIndex + 1 }}</span> dari {{ $this->answers->count() }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    {{-- Opponent progress --}}
                    <div class="min-w-[180px] rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2 text-[10px] font-bold uppercase tracking-wider text-indigo-600">
                            <span>Lawan</span>
                            <span>{{ $this->opponentProgress }}/{{ $session::TOTAL_QUESTIONS }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-indigo-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
                                 style="width: {{ $session::TOTAL_QUESTIONS > 0 ? round(($this->opponentProgress / $session::TOTAL_QUESTIONS) * 100) : 0 }}%"></div>
                        </div>
                        <p class="mt-1 text-[10px] text-indigo-500">
                            @if ($this->opponentProgress > 0)
                                Lawan sedang di Soal {{ $this->opponentProgress }}
                            @else
                                Lawan belum mulai
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2.5" wire:ignore>
                        <div x-data="examTimer({{ max(0, $this->remainingSeconds) }})">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Sisa Waktu</p>
                            <p class="text-xl font-bold tabular-nums text-rose-700" x-text="formattedTime"></p>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="submitDuel"
                            wire:confirm="Selesaikan duel sekarang? Skor akan dikunci."
                            class="ui-btn-danger shrink-0">
                        Selesai Duel
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="grid gap-6 xl:grid-cols-[220px_1fr]">
                <aside class="ui-card h-fit p-4 xl:sticky xl:top-28">
                    <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Navigasi</h2>
                    <div class="grid grid-cols-5 gap-1.5">
                        @foreach ($this->answers as $index => $answer)
                            <button type="button"
                                    wire:key="duel-nav-{{ $answer->id }}"
                                    wire:click="goToQuestion({{ $index }})"
                                    @class([
                                        'flex h-8 items-center justify-center rounded-lg text-xs font-bold transition',
                                        'bg-rose-600 text-white ring-2 ring-rose-300' => $currentIndex === $index,
                                        'bg-emerald-500 text-white' => $currentIndex !== $index && $answer->selected_option_id,
                                        'bg-slate-200 text-slate-600' => $currentIndex !== $index && ! $answer->selected_option_id,
                                    ])>{{ $index + 1 }}</button>
                        @endforeach
                    </div>
                </aside>

                <div class="space-y-4 min-w-0">
                    @if ($this->currentAnswer)
                        <div class="ui-card p-6 sm:p-8">
                            <div class="mb-5 flex flex-wrap items-center gap-2">
                                @php $code = $this->currentAnswer->question->subject->code->value; @endphp
                                <span @class([
                                    'ui-badge',
                                    'bg-blue-100 text-blue-700' => $code === 'twk',
                                    'bg-amber-100 text-amber-700' => $code === 'tiu',
                                    'bg-violet-100 text-violet-700' => $code === 'tkp',
                                ])>{{ $this->currentAnswer->question->subject->code->label() }}</span>
                            </div>

                            <div class="prose-exam mb-8 text-base">
                                {!! html_for_display($this->currentAnswer->question->content) !!}
                            </div>

                            <div class="space-y-3">
                                @foreach ($this->currentAnswer->question->options as $option)
                                    <label @class([
                                        'flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 transition',
                                        'border-rose-500 bg-rose-50/50 ring-4 ring-rose-500/10' => $selectedOptionId === $option->id,
                                        'border-slate-200 bg-white hover:border-slate-300' => $selectedOptionId !== $option->id,
                                    ])>
                                        <span @class([
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                                            'bg-rose-600 text-white' => $selectedOptionId === $option->id,
                                            'bg-slate-100 text-slate-600' => $selectedOptionId !== $option->id,
                                        ])>{{ $option->label }}</span>
                                        <input type="radio" wire:click="selectOption({{ $option->id }})" @checked($selectedOptionId === $option->id) class="sr-only">
                                        <span class="flex-1 pt-1 text-sm leading-relaxed text-slate-800">
                                            @if ($option->isImage())
                                                <img src="{{ $option->imageUrl() }}" alt="Pilihan {{ $option->label }}" class="max-h-48 max-w-full rounded-lg object-contain">
                                            @else
                                                {!! $option->content !!}
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" wire:click="previous" @disabled($currentIndex === 0) class="ui-btn-secondary disabled:opacity-40">← Sebelumnya</button>
                            <button type="button"
                                    wire:click="next"
                                    @disabled(! $selectedOptionId)
                                    @class(['ui-btn-primary', 'opacity-50 cursor-not-allowed' => ! $selectedOptionId])>
                                Simpan & Lanjutkan →
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    @endif
</div>
