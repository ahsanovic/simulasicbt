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
                    'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' => $this->answerStates[$currentIndex]['is_marked'] ?? false,
                ])>
            {{ ($this->answerStates[$currentIndex]['is_marked'] ?? false) ? '★ Hapus Tanda' : '☆ Tandai Soal' }}
        </button>
        <button type="button"
                wire:click="next"
                @disabled(! $selectedOptionId)
                @class([
                    'ui-btn-primary',
                    'opacity-50 cursor-not-allowed' => ! $selectedOptionId,
                ])>
            Simpan & Lanjutkan
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
</div>
