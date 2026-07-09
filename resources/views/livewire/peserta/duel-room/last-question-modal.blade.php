@if ($showLastQuestionModal)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="duel-last-question-modal-title"
        x-data
        x-on:keydown.escape.window="$wire.closeLastQuestionModal()"
    >
        <div
            class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"
            wire:click="closeLastQuestionModal"
        ></div>

        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-900/30">
            <div @class([
                'relative overflow-hidden px-6 pb-6 pt-6 text-white sm:px-8',
                'bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700' => $this->unansweredCount === 0,
                'bg-gradient-to-br from-amber-500 via-orange-500 to-rose-600' => $this->unansweredCount > 0,
            ])>
                <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
                <button
                    type="button"
                    wire:click="closeLastQuestionModal"
                    class="absolute right-4 top-4 rounded-xl p-2 text-white/80 transition hover:bg-white/15 hover:text-white"
                    aria-label="Tutup"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                        @if ($this->unansweredCount === 0)
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @endif
                    </div>
                    <div>
                        <h2 id="duel-last-question-modal-title" class="text-lg font-bold tracking-tight">Soal Terakhir</h2>
                        <p class="text-sm text-white/85">Soal {{ $currentIndex + 1 }} dari {{ $this->answers->count() }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 p-6 sm:p-8">
                @if ($this->unansweredCount === 0)
                    <div class="space-y-2 text-center">
                        <p class="text-base font-semibold text-slate-900">Semua soal sudah dijawab.</p>
                        <p class="text-sm text-slate-600">
                            Anda berada di soal terakhir dan jawaban sudah tersimpan. Selesaikan duel untuk mengunci skor.
                        </p>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-center">
                        <p class="text-base font-semibold text-amber-950">
                            Masih ada <span class="font-bold">{{ $this->unansweredCount }}</span> soal belum dijawab.
                        </p>
                        <p class="mt-2 text-sm text-amber-900/80">
                            Yakin ingin menyelesaikan duel sekarang?
                        </p>
                    </div>
                @endif

                <div class="flex flex-col gap-3 sm:flex-row-reverse">
                    <button
                        type="button"
                        wire:click="submitDuel"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-wait"
                        class="ui-btn-danger flex-1 justify-center"
                    >
                        <span wire:loading.remove wire:target="submitDuel">Selesai Duel</span>
                        <span wire:loading wire:target="submitDuel">Mengunci skor...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="goBackFromLastQuestionModal"
                        class="ui-btn-secondary flex-1 justify-center"
                    >
                        Kembali ke Soal Lain
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
