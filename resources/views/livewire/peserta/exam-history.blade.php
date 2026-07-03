<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    @if ($showResultModal && $resultAttempt)
        <x-exam-result-modal
            :attempt="$resultAttempt"
            :passing-grades="$passingGrades"
            :score-max="$scoreMax"
        />
    @endif

    <main @class(['mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8', 'overflow-hidden' => $showResultModal])>
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Riwayat Tes</h1>
            <p class="mt-2 max-w-lg text-primary-100">Semua hasil simulasi yang pernah Anda selesaikan tersimpan di sini.</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="ui-card p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Simulasi</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ number_format($stats['total']) }}</p>
                    </div>
                </div>
            </div>
            <div class="ui-card p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rata-rata Skor Total</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-primary-700">{{ format_exam_score($stats['average']) }}</p>
                    </div>
                </div>
            </div>
            <div class="ui-card p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Lulus Ambang Batas</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-emerald-700">{{ number_format($stats['passed']) }}</p>
                        @if ($stats['total'] > 0)
                            <p class="mt-1 text-xs text-slate-500">{{ round(($stats['passed'] / $stats['total']) * 100) }}% dari total simulasi</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <x-exam-passing-grades-banner :passing-grades="$passingGrades" class="mb-6" />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,30%)] lg:items-start">
            <div class="min-w-0 space-y-4">
            @forelse ($attempts as $attempt)
                @php
                    $passes = exam_attempt_passes(
                        $attempt->score_twk,
                        $attempt->score_tiu,
                        $attempt->score_tkp,
                        $attempt->total_score,
                    );
                @endphp
                <article wire:key="history-{{ $attempt->id }}" @class([
                    'ui-card group overflow-hidden transition duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary-500/10',
                    'border-l-4 border-l-emerald-500' => $passes,
                    'border-l-4 border-l-rose-400' => ! $passes,
                    'ring-2 ring-primary-400 ring-offset-2' => $resultAttempt && $attempt->id === $resultAttempt->id,
                ])>
                    <div class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-bold text-slate-900">{{ $attempt->exam->title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $attempt->submitted_at?->format('d M Y, H:i') ?? $attempt->created_at->format('d M Y, H:i') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'ui-badge inline-flex items-center gap-1',
                                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/60' => $passes,
                                'bg-rose-50 text-rose-700 ring-1 ring-rose-200/60' => ! $passes,
                            ])>
                                @if ($passes)
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Lulus Ambang Batas
                                @else
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Belum Lulus
                                @endif
                            </span>
                            <span @class([
                                'ui-badge',
                                'bg-emerald-50 text-emerald-600' => $attempt->status->value === 'submitted',
                                'bg-slate-100 text-slate-600' => $attempt->status->value !== 'submitted',
                            ])>{{ $attempt->status->label() }}</span>
                        </div>
                    </div>

                    <div class="grid gap-3 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
                        <x-exam-score-threshold
                            label="TWK"
                            :value="$attempt->score_twk"
                            :threshold="$passingGrades['twk']"
                            :max="$scoreMax['twk']"
                            color="blue"
                        />
                        <x-exam-score-threshold
                            label="TIU"
                            :value="$attempt->score_tiu"
                            :threshold="$passingGrades['tiu']"
                            :max="$scoreMax['tiu']"
                            color="amber"
                        />
                        <x-exam-score-threshold
                            label="TKP"
                            :value="$attempt->score_tkp"
                            :threshold="$passingGrades['tkp']"
                            :max="$scoreMax['tkp']"
                            color="violet"
                        />
                        <x-exam-score-threshold
                            label="Total"
                            :value="$attempt->total_score"
                            :threshold="$passingGrades['total']"
                            :max="$scoreMax['total']"
                            color="primary"
                        />
                    </div>

                    <div class="border-t border-slate-100 bg-slate-50/40 px-5 py-4">
                        <a href="{{ route('peserta.exam.review', $attempt) }}"
                           wire:navigate
                           class="ui-btn-primary inline-flex w-full items-center justify-center gap-2 sm:w-auto">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Kunci Jawaban dan Pembahasan
                        </a>
                    </div>
                </article>
            @empty
                <div class="ui-card flex flex-col items-center justify-center border-dashed px-6 py-16 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-50 text-primary-400">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
                    </div>
                    <p class="mt-4 font-semibold text-slate-700">Belum ada riwayat tes</p>
                    <p class="mt-1 text-sm text-slate-500">
                        Mulai simulasi dari menu
                        <a href="{{ route('peserta.dashboard') }}" wire:navigate class="font-semibold text-primary-600 hover:underline">Simulasi</a>.
                    </p>
                </div>
            @endforelse

            @if ($attempts->hasPages())
                <div class="mt-2">{{ $attempts->links() }}</div>
            @endif
            </div>

            <aside class="lg:sticky lg:top-6">
                <livewire:peserta.ai-readiness-report />
            </aside>
        </div>
    </main>
</div>
