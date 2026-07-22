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
                        <x-ui.coin-icon class="h-4 w-4 shrink-0 text-amber-300" />
                        <span class="flex flex-col items-start leading-tight">
                            <span class="tabular-nums">{{ number_format($coinBalance) }} koin</span>
                            <span class="text-[10px] font-medium text-primary-200/90 transition group-hover:text-white">Toko koin →</span>
                        </span>
                    </a>
                    @if ($dailyStreakInfo['streak'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20" title="Pengali XP konsistensi harian">
                            <svg class="h-4 w-4 text-orange-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                            {{ $dailyStreakInfo['streak'] }} hari · {{ $dailyStreakInfo['multiplier_label'] }} XP
                        </span>
                    @endif
                    @if ($flashcardDueCount > 0)
                        <a href="{{ route('peserta.kartu-sakti.index') }}"
                           wire:navigate
                           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-400/25 px-3 py-2 text-sm font-semibold ring-1 ring-amber-200/40 transition hover:bg-amber-400/35">
                            <span aria-hidden="true">✨</span>
                            {{ $flashcardDueCount }} kartu due
                        </a>
                    @endif
                    <a href="{{ route('peserta.rencana-belajar.index') }}"
                       wire:navigate
                       class="inline-flex items-center gap-1.5 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                        <svg class="h-4 w-4 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        @if ($plannerActiveCount > 0)
                            {{ $plannerCompletedToday }} selesai · {{ $plannerActiveCount }} rencana
                        @else
                            Rencana Belajar
                        @endif
                    </a>
                </div>
            </div>
        </div>

        <x-peserta.platform-features
            :has-history="$hasHistory"
            :planner-active-count="$plannerActiveCount"
            :planner-completed-today="$plannerCompletedToday"
        />

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-peserta.formation-matchmaking-summary-card variant="compact" :has-history="$hasHistory" :summary="$formationSummary" />
            <x-peserta.leaderboard-summary-card variant="compact" :ranks="$leaderboardRanks" />
            <x-peserta.devotion-badge-card variant="compact" :progress="$devotionProgress" :streak-info="$dailyStreakInfo" />
        </div>

        <div id="ujian-tersedia" class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-900">Ujian Tersedia</h2>
            <div class="flex items-center gap-2">
                <span class="ui-badge bg-primary-100 text-primary-700">{{ $exams->count() }} ujian</span>
                <a href="{{ route('peserta.simulasi.index') }}"
                   wire:navigate
                   class="text-sm font-semibold text-primary-600 transition hover:text-primary-700">
                    Halaman simulasi →
                </a>
            </div>
        </div>

        <x-peserta.exam-catalog-list :exams="$exams" />

        <section class="mt-10" aria-labelledby="devotion-detail-heading">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="devotion-detail-heading" class="text-lg font-bold text-slate-900">Detail Lencana Pengabdian</h2>
                <span class="text-xs font-medium text-slate-500">Tingkatan pangkat &amp; cara dapat XP</span>
            </div>
            <x-peserta.devotion-badge-card :progress="$devotionProgress" :streak-info="$dailyStreakInfo" />
        </section>
    </main>

    <x-peserta.exam-pin-modal :pin-exam-id="$pinExamId" />

    <x-exam-stress-test-modal :stress-test-exam-id="$stressTestExamId" />
</div>
