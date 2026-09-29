<div class="mx-auto max-w-xl px-4 py-10">
    <div class="ui-card p-6 sm:p-8 text-center">
        <div class="mb-2 flex items-start justify-between gap-3 text-left">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Hasil Ujian</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $attempt->event->name }}</p>
            </div>
            <x-peserta.mode-ujian-logout-button />
        </div>

        @if ($skdAttempt)
            <div class="mt-6 text-left">
                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">SKD</p>
                <div class="grid grid-cols-4 gap-2">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">TWK</p>
                        <p class="text-lg font-bold text-slate-900">{{ (int) $skdAttempt->score_twk }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">TIU</p>
                        <p class="text-lg font-bold text-slate-900">{{ (int) $skdAttempt->score_tiu }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] text-slate-500">TKP</p>
                        <p class="text-lg font-bold text-slate-900">{{ (int) $skdAttempt->score_tkp }}</p>
                    </div>
                    <div class="rounded-xl bg-primary-50 p-3">
                        <p class="text-[11px] text-primary-600">Total</p>
                        <p class="text-lg font-bold text-primary-700">{{ (int) $skdAttempt->total_score }}</p>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-6 text-left">
            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">SKB</p>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs text-slate-500">Jawaban Benar</p>
                    <p class="text-2xl font-bold text-slate-900">{{ (int) $attempt->correct_count }}</p>
                </div>
                <div class="rounded-xl bg-primary-50 p-4">
                    <p class="text-xs text-primary-600">Total Skor</p>
                    <p class="text-2xl font-bold text-primary-700">{{ (int) $attempt->total_score }}</p>
                </div>
            </div>
        </div>

        <p class="mt-6 text-sm font-semibold text-emerald-700">Ujian selesai. Terima kasih.</p>
    </div>
</div>
