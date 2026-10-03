<div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
    <button type="button"
            wire:click="previous"
            @disabled($currentIndex === 0)
            class="ui-btn-secondary order-2 sm:order-1 disabled:opacity-40">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Sebelumnya
    </button>

    <div class="flex flex-col gap-2 sm:order-2 sm:flex-row">
        <button type="button"
                wire:click="toggleMark"
                @class([
                    'ui-btn-secondary',
                    'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' => $answerStates[$currentIndex]['is_marked'] ?? false,
                ])>
            {{ ($answerStates[$currentIndex]['is_marked'] ?? false) ? '★ Hapus Tanda' : '☆ Tandai Soal' }}
        </button>

        @if ($currentIndex < count($answerStates) - 1)
            <button type="button" wire:click="next" class="ui-btn-primary">
                Simpan &amp; Lanjutkan
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        @else
            @if ($this->currentPickSaved)
                <span class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Jawaban Tersimpan
                </span>
            @else
                <button type="button"
                        wire:click="saveAnswer"
                        @disabled(! $selectedOptionId)
                        @class([
                            'ui-btn-primary',
                            'opacity-50 cursor-not-allowed' => ! $selectedOptionId,
                        ])>
                    Simpan Jawaban
                </button>
            @endif
            <button type="button" wire:click="submitExam" wire:confirm="{{ $this->submitConfirmMessage }}" class="ui-btn-danger">
                Selesai Ujian
            </button>
        @endif
    </div>
</div>
