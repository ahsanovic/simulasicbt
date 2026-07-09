<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                <div class="min-w-0 sm:flex-1">
                    <h1 class="truncate text-2xl font-bold tracking-tight">Halo, {{ auth()->user()->name }} 👋</h1>
                    <p class="mt-2 text-sm text-primary-100 sm:text-base">Ini adalah platform simulasi CBT BKD Jatim — Anda dapat mengulang tes berkali-kali.
                        <br> Setiap hasil tersimpan di riwayat tes.</p>
                    <p class="mt-3 text-sm font-medium text-white/95 sm:text-base">
                        @if ($devotionProgress['is_max_tier'])
                            Anda sudah mencapai kasta tertinggi — terus kumpulkan XP dan jadilah inspirasi pejuang CPNS lainnya.
                        @elseif (! $hasHistory)
                            Selesaikan simulasi pertama untuk mendapat <span class="font-bold text-amber-200">+{{ \App\Services\GamificationService::EXAM_PASS_XP_REWARD }} XP</span> dan mulai naik pangkat.
                        @else
                            Butuh <span class="font-bold text-amber-200">{{ number_format($devotionProgress['xp_to_next']) }} XP</span> lagi menuju <span class="font-bold">{{ $devotionProgress['next_badge']['label'] }}</span> — kerjakan simulasi (<span class="font-bold text-amber-200">+{{ \App\Services\GamificationService::EXAM_PASS_XP_REWARD }} XP</span>).
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                    <a href="#devotion-badge-card"
                       class="group inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                        <svg class="h-4 w-4 text-amber-300" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                        <span class="flex flex-col items-start leading-tight">
                            <span>{{ number_format($totalXp) }} XP</span>
                            <span class="text-[10px] font-medium text-primary-200/90 transition group-hover:text-white">Cara naik →</span>
                        </span>
                    </a>
                    <a href="{{ route('peserta.shop.index') }}"
                       wire:navigate
                       class="group inline-flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                        <span class="text-base leading-none" aria-hidden="true">🪙</span>
                        <span class="flex flex-col items-start leading-tight">
                            <span class="tabular-nums">{{ number_format($coinBalance) }} koin</span>
                            <span class="text-[10px] font-medium text-primary-200/90 transition group-hover:text-white">Toko koin →</span>
                        </span>
                    </a>
                    @if ($audioDailyStreak > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                            <svg class="h-4 w-4 text-orange-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                            {{ $audioDailyStreak }} hari streak
                        </span>
                    @endif
                    @if ($flashcardDueCount > 0)
                        <a href="{{ route('peserta.kartu-sakti.index') }}"
                           wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-400/25 px-3 py-2 text-sm font-semibold ring-1 ring-amber-200/40 transition hover:bg-amber-400/35">
                            <span aria-hidden="true">✨</span>
                            {{ $flashcardDueCount }} kartu due
                        </a>
                    @elseif ($flashcardDailyStreak > 0)
                        <a href="{{ route('peserta.kartu-sakti.index') }}"
                           wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                            <span aria-hidden="true">✨</span>
                            {{ $flashcardDailyStreak }} hari Kartu Sakti
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <x-peserta.platform-features :has-history="$hasHistory" />

        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Ujian Tersedia</h2>
            <span class="ui-badge bg-primary-100 text-primary-700">{{ $exams->count() }} ujian</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,26%)] lg:items-start">
            <div class="grid min-w-0 gap-4">
            @forelse ($exams as $exam)
                <article class="ui-card group overflow-hidden transition hover:shadow-lg hover:shadow-primary-500/10">
                    <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-slate-900 group-hover:text-primary-700">{{ $exam->title }}</h3>
                            @if($exam->description)
                                <p class="mt-1.5 text-sm text-slate-500 line-clamp-2">{{ $exam->description }}</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="ui-badge bg-slate-100 text-slate-700">
                                    <svg class="mr-1 inline h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $exam->duration_minutes }} menit
                                </span>
                                <span class="ui-badge bg-primary-50 text-primary-700">{{ $exam->questions_count }} soal</span>
                                @if($exam->attempt_count > 0)
                                    <span class="ui-badge bg-emerald-50 text-emerald-700">{{ $exam->attempt_count }}× dikerjakan</span>
                                    @if($exam->best_score !== null)
                                        <span class="ui-badge bg-amber-50 text-amber-800">Terbaik: <x-exam-score :value="$exam->best_score" /></span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                            @if($exam->in_progress_attempt)
                                <button wire:click="startExam({{ $exam->id }})"
                                        @disabled(! $exam->isAvailable())
                                        class="ui-btn-success px-6">
                                    Lanjutkan Simulasi →
                                </button>
                            @else
                                <button wire:click="startExam({{ $exam->id }})"
                                        @disabled(! $exam->isAvailable())
                                        @class([
                                            'ui-btn-success px-6',
                                            'opacity-50 cursor-not-allowed' => ! $exam->isAvailable(),
                                        ])>
                                    @if($exam->attempt_count > 0)
                                        Ulangi Simulasi →
                                    @elseif($exam->isAvailable())
                                        Mulai Simulasi →
                                    @else
                                        Belum Tersedia
                                    @endif
                                </button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="ui-card flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <x-ui.icon name="exams" class="h-8 w-8" />
                    </div>
                    <p class="mt-4 font-semibold text-slate-700">Belum ada ujian</p>
                    <p class="mt-1 text-sm text-slate-500">Belum ada ujian simulasi yang dipublikasikan.</p>
                </div>
            @endforelse
            </div>

            <aside class="space-y-4 lg:sticky lg:top-6">
                <x-peserta.leaderboard-summary-card :ranks="$leaderboardRanks" />
                <x-peserta.devotion-badge-card :progress="$devotionProgress" />
            </aside>
        </div>
    </main>
</div>
