<div class="min-h-screen {{ $mode === 'playing' ? 'bg-gradient-to-b from-amber-950 via-orange-950 to-slate-950' : 'bg-gradient-to-b from-slate-50 to-amber-50/30' }}">
    @if ($mode === 'setup')
        <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <x-ui.flash-toast />

            <div class="mb-8 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-rose-500 p-6 text-white shadow-xl shadow-amber-500/20 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-amber-100">Spaced Repetition</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight">Kartu Sakti ✨</h1>
                        <p class="mt-2 max-w-xl text-sm text-amber-50">
                            Kartu hafalan pintar berbasis ilmu spaced repetition — otomatis mengingatkan kapan materi harus direview agar masuk ingatan jangka panjang.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                        <span aria-hidden="true">⭐</span>
                        Metode Anki
                    </div>
                </div>
            </div>

            <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <span class="text-lg">📚</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kartu Due Hari Ini</p>
                        <p class="text-lg font-bold text-slate-900">{{ $dueCount }} kartu</p>
                    </div>
                </div>
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                        <span class="text-lg">🃏</span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Kartu Aktif</p>
                        <p class="text-lg font-bold text-slate-900">{{ $activeCount }} / {{ \App\Models\Flashcard::MAX_ACTIVE_CARDS }}</p>
                    </div>
                </div>
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Streak Konsistensi</p>
                        <p class="text-lg font-bold text-slate-900">
                            @if ($dailyStreak > 0)
                                {{ $dailyStreak }} hari · {{ $streakMultiplierLabel }}
                            @else
                                Belum ada
                            @endif
                        </p>
                    </div>
                </div>
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total XP</p>
                        <p class="text-lg font-bold text-slate-900">{{ number_format($totalXp) }} XP</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,32%)] lg:items-start">
                <div class="space-y-6">
                    <div class="ui-card p-6">
                        <h2 class="text-sm font-bold text-slate-900">Review {{ \App\Services\FlashcardService::DAILY_REVIEW_LIMIT }} Kartu Hari Ini</h2>
                        <p class="mt-1 text-xs text-slate-500">Jawab dalam hati, buka jawaban, lalu pilih seberapa hafal Anda.</p>

                        <div class="mt-5 rounded-2xl border border-amber-100 bg-amber-50/60 p-5">
                            <p class="text-sm font-semibold text-amber-900">Cara kerja interval review:</p>
                            <ul class="mt-3 space-y-2 text-xs text-amber-950/80">
                                <li><strong>Lupa 😰</strong> → muncul lagi besok</li>
                                <li><strong>Agak ingat 🤔</strong> → muncul lagi 3 hari</li>
                                <li><strong>Sudah hafal ✅</strong> → 1 → 3 → 7 → 14 → 30 → 60 hari</li>
                            </ul>
                        </div>

                        @if ($this->mostForgotten->isNotEmpty())
                            <div class="mt-6">
                                <h3 class="text-sm font-bold text-slate-900">Kartu Paling Sering Lupa</h3>
                                <p class="mt-1 text-xs text-slate-500">Prioritaskan materi ini — otak Anda paling sering gagal mengingatnya.</p>
                                <ul class="mt-4 space-y-2">
                                    @foreach ($this->mostForgotten as $card)
                                        <li class="flex items-center justify-between gap-3 rounded-xl border border-rose-100 bg-rose-50/50 px-4 py-3 text-sm">
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900">{{ $card->displayTitle() }}</p>
                                                <p class="text-xs text-slate-500">{{ $card->source_type->label() }} · {{ $card->subject_code->label() }}</p>
                                            </div>
                                            <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700">
                                                {{ $card->forget_count }}× lupa
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    @if ($this->weakSeedPreview['available'] > 0)
                        <div class="ui-card border-dashed border-amber-200 p-6">
                            <h2 class="text-sm font-bold text-slate-900">Auto-Seed dari Materi Lemah</h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Kami menemukan {{ $this->weakSeedPreview['available'] }} soal dari materi yang masih lemah di evaluasi Anda.
                            </p>
                            <button type="button"
                                    wire:click="seedFromWeakMaterials"
                                    wire:loading.attr="disabled"
                                    class="ui-btn-secondary mt-4 border-amber-200 text-amber-800 hover:bg-amber-50">
                                <span wire:loading.remove wire:target="seedFromWeakMaterials">⭐ Simpan Semua ke Kartu Sakti</span>
                                <span wire:loading wire:target="seedFromWeakMaterials">Menyimpan...</span>
                            </button>
                        </div>
                    @endif
                </div>

                <aside class="ui-card sticky top-24 p-6">
                    <h2 class="text-sm font-bold text-slate-900">Mulai Review</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Kartu due</dt>
                            <dd class="font-bold text-slate-900">{{ min($dueCount, \App\Services\FlashcardService::DAILY_REVIEW_LIMIT) }} kartu</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Estimasi</dt>
                            <dd class="font-bold text-slate-900">~{{ max(3, min($dueCount, \App\Services\FlashcardService::DAILY_REVIEW_LIMIT) * 1) }} menit</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Reward</dt>
                            <dd class="font-bold text-amber-700">+1 XP per kartu</dd>
                        </div>
                    </dl>

                    <button type="button"
                            wire:click="startReview"
                            wire:loading.attr="disabled"
                            @disabled($dueCount === 0)
                            class="ui-btn-primary mt-6 w-full justify-center bg-amber-600 hover:bg-amber-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="startReview">Mulai Review Kartu</span>
                        <span wire:loading wire:target="startReview">Menyiapkan kartu...</span>
                    </button>

                    @error('review')
                        <p class="mt-3 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror

                    @if ($dueCount === 0 && $activeCount === 0)
                        <p class="mt-4 text-xs leading-relaxed text-slate-500">
                            Belum ada kartu. Simpan soal salah dari pembahasan ujian atau cheat-sheet materi belajar dengan tombol ⭐.
                        </p>
                    @endif
                </aside>
            </div>
        </main>

    @elseif ($mode === 'playing')
        <main class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-6 sm:py-10">
            <div class="mb-6 flex items-center justify-between gap-3 text-white/70">
                <button type="button"
                        wire:click="finishSession"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/20">
                    Akhiri Sesi
                </button>
                <span class="text-xs font-semibold">{{ $currentIndex + 1 }} / {{ count($cardIds) }}</span>
            </div>

            <div class="mb-2 h-1 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-amber-400 transition-all duration-500"
                     style="width: {{ count($cardIds) > 0 ? (($currentIndex + ($revealed ? 0.5 : 0)) / count($cardIds)) * 100 : 0 }}%"></div>
            </div>

            @if ($this->currentCard)
                <div class="flex-1">
                    <div class="rounded-3xl bg-gradient-to-br from-amber-600/30 via-orange-700/20 to-rose-900/30 p-6 ring-1 ring-white/10 backdrop-blur-sm sm:p-8">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-200">
                                {{ $revealed ? 'Jawaban' : 'Pertanyaan' }}
                            </span>
                            <span class="text-xs font-semibold text-amber-200">
                                {{ $this->currentCard->subject_code->label() }} · {{ $this->currentCard->source_type->label() }}
                            </span>
                        </div>

                        @if (! $revealed)
                            <div class="prose prose-invert prose-sm max-w-none text-white/90">
                                <div>{!! html_for_display($this->currentCard->front) !!}</div>
                            </div>
                            <p class="mt-6 text-center text-sm text-amber-100/80">Jawab dalam hati, lalu buka jawaban.</p>
                        @else
                            <div class="prose prose-invert prose-sm max-w-none text-white/90">
                                <div>{!! $this->currentCard->back !!}</div>
                            </div>
                            <p class="mt-6 text-center text-sm text-amber-100/80">Seberapa hafal Anda dengan materi ini?</p>
                        @endif
                    </div>
                </div>

                <div class="mt-8 space-y-3">
                    @if (! $revealed)
                        <button type="button"
                                wire:click="revealAnswer"
                                class="ui-btn-primary w-full justify-center bg-amber-500 py-3.5 text-base hover:bg-amber-400">
                            Buka Jawaban
                        </button>
                    @else
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (\App\Enums\FlashcardRating::cases() as $rating)
                                <button type="button"
                                        wire:click="rateCard('{{ $rating->value }}')"
                                        wire:loading.attr="disabled"
                                        class="rounded-2xl border border-white/10 bg-white/10 px-4 py-4 text-sm font-semibold text-white transition hover:bg-white/20">
                                    <span class="block text-xl">{{ $rating->emoji() }}</span>
                                    <span class="mt-1 block">{{ $rating->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </main>

    @elseif ($mode === 'finished')
        <main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10">
            <div class="w-full rounded-3xl bg-white p-8 text-center shadow-xl shadow-amber-500/10 ring-1 ring-slate-200">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-3xl">
                    ✨
                </div>
                <h1 class="mt-5 text-2xl font-bold text-slate-900">Mantap! Otak Anda semakin tajam.</h1>
                <p class="mt-2 text-sm text-slate-500">Sesi Kartu Sakti selesai — materi akan muncul lagi sesuai jadwal spaced repetition.</p>

                <dl class="mt-8 space-y-4 rounded-2xl bg-slate-50 p-5 text-left text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Durasi review</dt>
                        <dd class="font-bold text-slate-900">{{ format_exam_remaining_time($summaryDurationSeconds ?? 0) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Kartu direview</dt>
                        <dd class="font-bold text-slate-900">{{ $summaryXp ?? 0 }} kartu</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Reward sesi ini</dt>
                        <dd class="font-bold text-amber-700">
                            +{{ $summaryXp ?? 0 }} XP
                            @if (($summaryBaseXp ?? 0) > 0 && ($summaryXp ?? 0) > ($summaryBaseXp ?? 0))
                                <span class="text-xs font-semibold text-amber-500">({{ $summaryBaseXp }} × {{ $streakMultiplierLabel }})</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <dt class="text-slate-500">Kartu due besok</dt>
                        <dd class="font-bold text-slate-900">{{ max(0, $dueCount) }} kartu</dd>
                    </div>
                    @if ($dailyStreak > 0)
                        <div class="rounded-xl bg-amber-50 px-4 py-3 text-center text-sm font-semibold text-amber-800">
                            🔥 {{ $dailyStreak }} hari streak konsistensi · pengali XP {{ $streakMultiplierLabel }} aktif hari ini!
                        </div>
                    @endif
                </dl>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <button type="button" wire:click="backToSetup" class="ui-btn-primary flex-1 justify-center bg-amber-600 hover:bg-amber-700">
                        Kembali
                    </button>
                    <a href="{{ route('peserta.dashboard') }}" wire:navigate class="ui-btn-secondary flex-1 justify-center">
                        Dashboard
                    </a>
                </div>
            </div>
        </main>
    @endif
</div>
