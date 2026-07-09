<div class="min-h-screen {{ $mode === 'playing' ? 'bg-slate-950' : 'bg-gradient-to-b from-slate-50 to-violet-50/30' }}">
    @if ($mode === 'setup')
        <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <x-ui.flash-toast />

            <div class="mb-8 rounded-2xl bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-600 p-6 text-white shadow-xl shadow-violet-500/20 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-violet-200">Learning Passive Mode</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight">Audio Mode</h1>
                        <p class="mt-2 max-w-xl text-sm text-violet-100">
                            Mode review flashcard audio — dengarkan soal, berpikir sejenak, lalu dengarkan pembahasan. Cocok untuk belajar hands-free.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                        <svg class="h-4 w-4 text-violet-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                        Zero-Interaction Friendly
                    </div>
                </div>
            </div>

            <div class="mb-6 grid gap-3 sm:grid-cols-2">
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total XP Belajar</p>
                        <p class="text-lg font-bold text-slate-900">{{ number_format($totalXp) }} XP</p>
                    </div>
                </div>
                <div class="ui-card flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Streak Konsistensi</p>
                        <p class="text-lg font-bold text-slate-900">
                            @if ($dailyStreak > 0)
                                {{ $dailyStreak }} hari · {{ $streakMultiplierLabel }}
                            @else
                                Belum ada streak
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,32%)] lg:items-start">
                <div class="space-y-6">
                    <div class="ui-card p-6">
                        <h2 class="text-sm font-bold text-slate-900">Pilih Topik Materi</h2>
                        <p class="mt-1 text-xs text-slate-500">Soal diambil acak dari bank soal aktif sesuai kategori.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ([
                                'twk' => ['label' => 'TWK', 'desc' => 'Wawasan Kebangsaan', 'color' => 'violet'],
                                'tiu' => ['label' => 'TIU', 'desc' => 'Verbal & Figural (tanpa hitungan)', 'color' => 'indigo'],
                                'tkp' => ['label' => 'TKP', 'desc' => 'Karakteristik Pribadi', 'color' => 'purple'],
                            ] as $code => $meta)
                                <label @class([
                                    'relative cursor-pointer rounded-xl border-2 p-4 transition',
                                    'border-violet-500 bg-violet-50 ring-2 ring-violet-500/20' => $subjectCode === $code,
                                    'border-slate-200 hover:border-slate-300' => $subjectCode !== $code,
                                ])>
                                    <input type="radio" wire:model.live="subjectCode" value="{{ $code }}" class="sr-only">
                                    <p class="text-sm font-bold text-slate-900">{{ $meta['label'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $meta['desc'] }}</p>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="ui-card p-6">
                        <h2 class="text-sm font-bold text-slate-900">Pilih Kuota Soal</h2>
                        <p class="mt-1 text-xs text-slate-500">Default 20 soal jika tidak diubah — target belajar realistis dan cepat selesai.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach (\App\Livewire\Peserta\AudioMode::PACKAGES as $limit => $package)
                                <label @class([
                                    'relative cursor-pointer rounded-xl border-2 p-4 transition',
                                    'border-violet-500 bg-violet-50 ring-2 ring-violet-500/20' => $questionLimit === $limit,
                                    'border-slate-200 hover:border-slate-300' => $questionLimit !== $limit,
                                ])>
                                    <input type="radio" wire:model.live="questionLimit" value="{{ $limit }}" class="sr-only">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-bold text-slate-900">{{ $package['label'] }}</p>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ $limit }} soal</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $package['description'] }}</p>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <aside class="ui-card sticky top-24 p-6">
                    <h2 class="text-sm font-bold text-slate-900">Ringkasan Sesi</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Topik</dt>
                            <dd class="font-bold text-slate-900">{{ $this->subjectLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Jumlah soal</dt>
                            <dd class="font-bold text-slate-900">{{ $questionLimit }} soal</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Estimasi</dt>
                            <dd class="font-bold text-slate-900">~{{ max(5, (int) round($questionLimit * 0.6)) }} menit</dd>
                        </div>
                    </dl>

                    <button type="button"
                            wire:click="startSession"
                            wire:loading.attr="disabled"
                            class="ui-btn-primary mt-6 w-full justify-center bg-violet-600 hover:bg-violet-700">
                        <span wire:loading.remove wire:target="startSession" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            Mulai Audio Mode
                        </span>
                        <span wire:loading wire:target="startSession">Menyiapkan soal...</span>
                    </button>

                    @error('subject')
                        <p class="mt-3 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </aside>
            </div>
        </main>

    @elseif ($mode === 'playing')
        <div
            wire:ignore
            x-data="audioModePlayer({
                questions: @js($questionsPayload),
                thinkingSeconds: 7,
                transitionSeconds: 2,
                optionPauseMs: 500,
                answerRevealPauseMs: 1200,
                autoplay: true,
            })"
            x-on:destroy.window="destroy()"
            class="mx-auto flex min-h-screen max-w-lg flex-col px-4 py-6 sm:py-10"
        >
            <div class="mb-6 flex items-center justify-between gap-3 text-white/70">
                <button type="button"
                        @click="endSessionEarly()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Akhiri Sesi
                </button>
                <span class="text-xs font-semibold" x-text="`${currentIndex + 1} / ${questions.length}`"></span>
            </div>

            <div class="mb-2 h-1 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-violet-400 transition-all duration-500" :style="`width: ${progressPercent}%`"></div>
            </div>

            <div class="flex-1">
                <div class="rounded-3xl bg-gradient-to-br from-violet-600/30 via-purple-700/20 to-indigo-900/40 p-6 ring-1 ring-white/10 backdrop-blur-sm sm:p-8">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-violet-200" x-text="stageLabel"></span>
                        <span class="text-xs font-semibold text-violet-200">{{ $this->subjectLabel }}</span>
                    </div>

                    <template x-if="currentQuestion">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-violet-300/80" x-text="`Soal ${currentQuestion.number}`"></p>

                            <div class="prose prose-invert prose-sm mt-3 max-w-none text-white/90"
                                 x-show="['question', 'options', 'thinking', 'answer'].includes(stage)">
                                <div x-html="currentQuestion.question_html"></div>
                            </div>

                            <div x-show="stage === 'thinking'" class="mt-6 flex flex-col items-center justify-center rounded-2xl bg-white/5 py-5 ring-1 ring-white/10">
                                <p class="text-sm font-semibold text-violet-200">Waktu berpikir</p>
                                <p class="mt-2 text-5xl font-black tabular-nums text-white" x-text="countdown"></p>
                                <p class="mt-3 text-center text-xs text-violet-200/80">Jawab dalam hati, atau ketuk opsi di bawah jika ingin.</p>
                            </div>

                            <div class="mt-6 space-y-2" x-show="stage === 'thinking' || stage === 'answer'">
                                <template x-for="option in currentQuestion.options" :key="option.label">
                                    <button type="button"
                                            @click="selectOption(option.label)"
                                            :class="{
                                                'border-violet-400 bg-violet-500/20': selectedOption === option.label,
                                                'border-emerald-400/60 bg-emerald-500/10': stage === 'answer' && option.is_correct,
                                                'border-white/10 bg-white/5': selectedOption !== option.label && !(stage === 'answer' && option.is_correct),
                                            }"
                                            class="flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left text-sm text-white/90 transition">
                                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white/10 text-xs font-bold" x-text="option.label"></span>
                                        <span class="min-w-0 flex-1">
                                            <template x-if="option.is_image && option.image_url">
                                                <img :src="option.image_url" :alt="`Opsi ${option.label}`" class="max-h-24 rounded-lg">
                                            </template>
                                            <template x-if="!option.is_image">
                                                <span x-text="option.text"></span>
                                            </template>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-8">
                <div class="flex items-center justify-center gap-6">
                    <button type="button"
                            @click="previous()"
                            :disabled="currentIndex === 0"
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-30">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                    </button>

                    <button type="button"
                            @click="togglePlayPause()"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-violet-500 text-white shadow-lg shadow-violet-500/40 transition hover:bg-violet-400">
                        <svg x-show="!isPlaying" class="h-7 w-7" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <svg x-show="isPlaying" class="h-7 w-7" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>

                    <button type="button"
                            @click="next()"
                            :disabled="currentIndex >= questions.length - 1"
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-30">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                    </button>
                </div>

                <p class="mt-4 text-center text-xs text-white/50">
                    Mode pasif: letakkan perangkat dan biarkan audio berjalan otomatis.
                </p>
            </div>
        </div>

    @elseif ($mode === 'finished')
        <main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10">
            <div class="w-full rounded-3xl bg-white p-8 text-center shadow-xl shadow-violet-500/10 ring-1 ring-slate-200">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-100 text-3xl">
                    🎧
                </div>
                <h1 class="mt-5 text-2xl font-bold text-slate-900">Mantap! Kamu sudah belajar secara produktif hari ini.</h1>
                <p class="mt-2 text-sm text-slate-500">Sesi Audio Mode selesai — terus pertahankan ritme belajarmu.</p>

                <dl class="mt-8 space-y-4 rounded-2xl bg-slate-50 p-5 text-left text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Durasi mendengar</dt>
                        <dd class="font-bold text-slate-900">{{ format_exam_remaining_time($summaryDurationSeconds ?? 0) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Soal yang dipelajari</dt>
                        <dd class="font-bold text-slate-900">{{ $summaryXp ?? 0 }} Soal {{ $this->subjectLabel }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Reward sesi ini</dt>
                        <dd class="font-bold text-violet-700">
                            +{{ $summaryXp ?? 0 }} XP
                            @if (($summaryBaseXp ?? 0) > 0 && ($summaryXp ?? 0) > ($summaryBaseXp ?? 0))
                                <span class="text-xs font-semibold text-violet-500">({{ $summaryBaseXp }} × {{ $streakMultiplierLabel }})</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                        <dt class="text-slate-500">Total XP belajar</dt>
                        <dd class="font-bold text-slate-900">{{ number_format($totalXp) }} XP</dd>
                    </div>
                    @if ($dailyStreak > 0)
                        <div class="rounded-xl bg-violet-50 px-4 py-3 text-center text-sm font-semibold text-violet-800">
                            🔥 {{ $dailyStreak }} hari streak konsistensi · pengali XP {{ $streakMultiplierLabel }} aktif hari ini!
                        </div>
                    @endif
                </dl>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <button type="button" wire:click="backToSetup" class="ui-btn-primary flex-1 justify-center bg-violet-600 hover:bg-violet-700">
                        Sesi Baru
                    </button>
                    <a href="{{ route('peserta.dashboard') }}" wire:navigate class="ui-btn-secondary flex-1 justify-center">
                        Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </main>
    @endif
</div>
