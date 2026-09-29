<div class="mx-auto max-w-xl px-4 py-10">
    <div class="ui-card p-6 sm:p-8 text-center">
        <div class="mb-2 flex items-start justify-between gap-3 text-left">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Hasil SKD</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $attempt->event->name }}</p>
            </div>
            <x-peserta.mode-ujian-logout-button />
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs text-slate-500">TWK</p>
                <p class="text-2xl font-bold text-slate-900">{{ (int) $attempt->score_twk }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs text-slate-500">TIU</p>
                <p class="text-2xl font-bold text-slate-900">{{ (int) $attempt->score_tiu }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs text-slate-500">TKP</p>
                <p class="text-2xl font-bold text-slate-900">{{ (int) $attempt->score_tkp }}</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-primary-50 p-4">
            <p class="text-xs text-primary-600">Total Skor</p>
            <p class="text-3xl font-extrabold text-primary-700">{{ (int) $attempt->total_score }}</p>
        </div>

        <div class="mt-6">
            @if ($attempt->event->exam_mode->includesSkb())
                <p class="mb-3 text-sm text-slate-600">Silakan lanjutkan ke tahap SKB.</p>
                <button wire:click="continueToNext" class="ui-btn-primary w-full justify-center">Lanjutkan ke SKB</button>
            @else
                <p class="text-sm font-semibold text-emerald-700">Ujian selesai. Terima kasih.</p>
            @endif
        </div>
    </div>
</div>
