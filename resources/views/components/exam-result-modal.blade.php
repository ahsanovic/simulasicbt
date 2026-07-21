@props([
    'attempt',
    'passingGrades',
    'scoreMax',
    'wrongCount' => 0,
    'remedialUnlock' => null,
    'totalXp' => 0,
    'formationName' => null,
])

@php
    $isRemedial = $attempt->isRemedial();
    $isDrill = $attempt->isDrill();
    $passes = ! $isRemedial && ! $isDrill && exam_attempt_passes(
        $attempt->score_twk,
        $attempt->score_tiu,
        $attempt->score_tkp,
        $attempt->total_score,
    );

    $xpEarned = 0;
    if ($isRemedial) {
        $totalQuestions = $attempt->answers->count();
        $correctCount = $attempt->correctAnswerCount();
        if ($totalQuestions > 0 && $correctCount === $totalQuestions) {
            $xpEarned = \App\Services\GamificationService::REMEDIAL_PERFECT_XP_REWARD;
        }
    } elseif ($isDrill) {
        $xpEarned = \App\Services\GamificationService::DRILL_XP_REWARD;
    } else {
        $xpEarned = $passes
            ? \App\Services\GamificationService::EXAM_PASS_XP_REWARD
            : \App\Services\GamificationService::EXAM_FAIL_XP_REWARD;
    }

    $subjects = [
        ['key' => 'twk', 'label' => 'TWK', 'color' => 'blue', 'score' => $attempt->score_twk],
        ['key' => 'tiu', 'label' => 'TIU', 'color' => 'amber', 'score' => $attempt->score_tiu],
        ['key' => 'tkp', 'label' => 'TKP', 'color' => 'violet', 'score' => $attempt->score_tkp],
        ['key' => 'total', 'label' => 'Total', 'color' => 'primary', 'score' => $attempt->total_score],
    ];
@endphp

<div
    class="fixed inset-0 z-50 overflow-y-auto p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="exam-result-title"
    x-data
    x-on:keydown.escape.window="$wire.closeResultModal()"
>
    <div
        class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"
        wire:click="closeResultModal"
    ></div>

    <div class="relative mx-auto flex min-h-full w-full max-w-4xl items-center justify-center">
        <div class="relative flex w-full max-h-[min(94dvh,920px)] min-h-[min(72dvh,640px)] flex-col overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-900/30 sm:min-h-[min(78dvh,720px)]">
        {{-- Header --}}
        <div @class([
            'relative shrink-0 overflow-hidden px-6 pb-5 pt-4 text-white sm:px-8 sm:pb-6 sm:pt-6',
            'bg-gradient-to-br from-indigo-500 via-violet-600 to-purple-700' => $isRemedial,
            'bg-gradient-to-br from-teal-500 via-emerald-600 to-cyan-700' => $isDrill,
            'bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700' => ! $isRemedial && ! $isDrill && $passes,
            'bg-gradient-to-br from-rose-500 via-orange-500 to-amber-600' => ! $isRemedial && ! $isDrill && ! $passes,
        ])>
            <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-12 -left-6 h-32 w-32 rounded-full bg-white/10"></div>

            <button
                type="button"
                wire:click="closeResultModal"
                class="absolute right-4 top-4 rounded-xl p-2 text-white/80 transition hover:bg-white/15 hover:text-white"
                aria-label="Tutup"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="relative flex flex-col items-center text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 shadow-lg backdrop-blur-sm">
                    @if ($isRemedial)
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    @elseif ($isDrill)
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    @elseif ($passes)
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </div>

                <p class="text-sm font-medium uppercase tracking-widest text-white/80">
                    {{ $isDrill ? 'Drill Selesai' : ($isRemedial ? 'Remedial Selesai' : 'Simulasi Selesai') }}
                </p>
                <h2 id="exam-result-title" class="mt-1 text-xl font-bold sm:text-2xl">{{ $attempt->displayTitle() }}</h2>
                @if ($attempt->event)
                    <p class="mt-1 text-xs font-medium text-white/70">Event Offline · {{ $attempt->exam->title }}</p>
                @endif
                <p class="mt-2 text-sm text-white/80">
                    {{ $attempt->submitted_at?->format('d M Y, H:i') ?? $attempt->created_at->format('d M Y, H:i') }}
                </p>

                <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/20 px-4 py-1.5 text-sm font-semibold backdrop-blur-sm">
                    @if ($isRemedial || $isDrill)
                        @php
                            $totalQuestions = $attempt->answers->count();
                            $correctCount = $attempt->correctAnswerCount();
                        @endphp
                        {{ $correctCount }} / {{ $totalQuestions }} soal benar
                    @elseif ($passes)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Lulus Ambang Batas
                    @else
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        Belum Lulus Ambang Batas
                    @endif
                </div>

                @if ($xpEarned > 0)
                <p class="mt-2 text-sm font-semibold text-white/90">
                    +{{ number_format($xpEarned) }} XP
                </p>
                @endif

                @if (! $isRemedial && ! $isDrill)
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-4xl font-black tabular-nums tracking-tight sm:text-5xl">{{ format_exam_score($attempt->total_score) }}</span>
                    <span class="text-lg text-white/70">/ {{ $passingGrades['total'] }}</span>
                </div>
                <p class="mt-1 text-xs text-white/70">Skor Total</p>
                @endif
            </div>
        </div>

        {{-- Score breakdown --}}
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-8 sm:py-7">
            @if ($isRemedial || $isDrill)
                <div @class([
                    'rounded-2xl border p-5 text-center',
                    'border-indigo-100 bg-indigo-50/60' => $isRemedial,
                    'border-teal-100 bg-teal-50/60' => $isDrill,
                ])>
                    <p @class([
                        'text-sm font-semibold',
                        'text-indigo-900' => $isRemedial,
                        'text-teal-900' => $isDrill,
                    ])>
                        {{ $isDrill ? 'Hasil Drill Soal' : 'Drill Soal Salah' }}
                    </p>
                    <p class="mt-2 text-3xl font-black tabular-nums text-slate-900">
                        {{ $attempt->correctAnswerCount() }} / {{ $attempt->answers->count() }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">soal dijawab dengan benar</p>
                    @if ($isRemedial && $xpEarned > 0)
                        <p class="mt-3 text-sm font-semibold text-emerald-600">Sempurna! Semua soal remedial benar.</p>
                    @elseif ($isDrill)
                        <p class="mt-3 text-sm text-slate-600">Buka pembahasan untuk evaluasi mendalam.</p>
                    @endif
                </div>
            @else
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Rincian Skor</p>
                <p class="text-xs text-slate-500">Bandingkan dengan ambang batas</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($subjects as $subject)
                    @php
                        $score = $subject['score'] !== null && $subject['score'] !== '' ? (int) round((float) $subject['score']) : null;
                        $threshold = $passingGrades[$subject['key']];
                        $subjectPasses = $score !== null && $score >= $threshold;
                        $max = $scoreMax[$subject['key']];
                        $pct = $score !== null ? min(100, round(($score / $max) * 100)) : 0;
                        $thresholdPct = min(100, round(($threshold / $max) * 100));

                        $accent = match ($subject['color']) {
                            'blue' => 'text-blue-600 bg-blue-50 border-blue-100',
                            'amber' => 'text-amber-600 bg-amber-50 border-amber-100',
                            'violet' => 'text-violet-600 bg-violet-50 border-violet-100',
                            default => 'text-primary-600 bg-primary-50 border-primary-100',
                        };

                        $barColor = match ($subject['color']) {
                            'blue' => 'bg-blue-500',
                            'amber' => 'bg-amber-500',
                            'violet' => 'bg-violet-500',
                            default => 'bg-primary-600',
                        };
                    @endphp

                    <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span @class(['inline-flex rounded-lg border px-2.5 py-1 text-xs font-bold uppercase tracking-wider', $accent])>
                                {{ $subject['label'] }}
                            </span>
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                'bg-emerald-100 text-emerald-700' => $subjectPasses,
                                'bg-red-100 text-red-700' => ! $subjectPasses && $score !== null,
                            ])>
                                @if ($subjectPasses)
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Lulus
                                @elseif ($score !== null)
                                    −{{ $threshold - $score }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-bold tabular-nums text-slate-900">{{ $score ?? '—' }}</span>
                            <span class="text-sm text-slate-400">/ {{ $threshold }}</span>
                        </div>

                        <div class="relative mt-3 h-2 overflow-hidden rounded-full bg-slate-200/80">
                            <div class="{{ $barColor }} h-full rounded-full transition-all duration-700" style="width: {{ $pct }}%"></div>
                            <div class="absolute top-0 bottom-0 w-0.5 bg-slate-500/60" style="left: {{ $thresholdPct }}%"></div>
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500">
                            Ambang batas <span class="font-semibold text-slate-600">{{ $threshold }}</span>
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <p class="text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Catatan:</span>
                    Anda dinyatakan lulus jika <strong>semua</strong> komponen (TWK, TIU, TKP, dan Total) memenuhi ambang batas masing-masing.
                </p>
            </div>

            @if ($attempt->stress_test_enabled && ($attempt->stress_test_analysis['has_data'] ?? false))
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <p class="text-sm font-semibold text-rose-900">
                        Mode Stress-Test aktif — Ketahanan Stres: {{ $attempt->stress_test_analysis['score'] }}% ({{ $attempt->stress_test_analysis['level_label'] }})
                    </p>
                    <p class="mt-1 text-xs text-rose-800/80">Lihat analisis lengkap di Kunci Jawaban dan Pembahasan.</p>
                </div>
            @endif

            <div class="rounded-2xl border border-teal-200 bg-gradient-to-r from-teal-50 via-cyan-50/80 to-primary-50/60 p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-teal-900">Simulasi Kelulusan Formasi</p>
                        @if ($formationName)
                            <p class="mt-1 text-xs leading-relaxed text-teal-800/90">
                                Lihat posisi kompetitif skor Anda untuk jabatan <span class="font-semibold">{{ $formationName }}</span>.
                            </p>
                        @else
                            <p class="mt-1 text-xs leading-relaxed text-teal-800/90">
                                Sudah punya target jabatan? Bandingkan skor Anda dengan pelamar jabatan yang sama di aplikasi ini.
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('peserta.simulasi-formasi') }}"
                       wire:navigate
                       class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-300/40 transition hover:bg-teal-700">
                        {{ $formationName ? 'Lihat posisi saya' : 'Pilih target jabatan' }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endif
        </div>

        {{-- Sticky footer: Kartu Sakti + action buttons --}}
        <div class="shrink-0 space-y-4 border-t border-slate-200 bg-white px-5 py-5 sm:px-8 sm:py-6">
            @if (! $isRemedial && $wrongCount > 0)
                <div class="grid gap-4 lg:grid-cols-2">
                    @if (! $attempt->isDuelAttempt())
                        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 sm:p-5">
                            <p class="text-sm font-semibold text-indigo-900">Ujian Remedial — Drill Soal Salah</p>
                            <p class="mt-1 text-xs text-indigo-800/80">
                                Ulangi hanya {{ $wrongCount }} soal yang salah tanpa mengerjakan seluruh paket.
                            </p>
                            @if ($remedialUnlock)
                                <div class="mt-3">
                                    <x-peserta.remedial-action
                                        :attempt="$attempt"
                                        :remedial-unlock="$remedialUnlock"
                                        :total-xp="$totalXp"
                                    />
                                </div>
                            @endif
                        </div>
                    @endif

                    <div @class([
                        'rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5',
                        'lg:col-span-2' => $attempt->isDuelAttempt(),
                    ])>
                        <p class="text-sm font-semibold text-amber-900">Kartu Sakti — Spaced Repetition</p>
                        <p class="mt-1 text-xs text-amber-800/80">
                            Simpan {{ $wrongCount }} soal salah agar otomatis direview sebelum Anda lupa.
                        </p>
                        <button type="button"
                                wire:click="saveResultWrongToFlashcard"
                                wire:loading.attr="disabled"
                                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600">
                            <span wire:loading.remove wire:target="saveResultWrongToFlashcard">⭐ Simpan ke Kartu Sakti</span>
                            <span wire:loading wire:target="saveResultWrongToFlashcard">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('peserta.exam.review', $attempt) }}"
                   wire:navigate
                   class="ui-btn-success flex-1 py-3 text-center text-sm font-semibold">
                    Kunci Jawaban dan Pembahasan
                </a>
                <button
                    type="button"
                    wire:click="closeResultModal"
                    class="ui-btn-primary flex-1 py-3 text-sm font-semibold"
                >
                    Tutup & Lihat Riwayat
                </button>
            </div>
        </div>
        </div>
    </div>
</div>
