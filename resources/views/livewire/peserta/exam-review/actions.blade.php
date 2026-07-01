<div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
    <button type="button"
            wire:click="previous"
            @disabled($currentIndex === 0)
            class="ui-btn-secondary order-2 sm:order-1 disabled:opacity-40">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Sebelumnya
    </button>

    <button type="button"
            wire:click="next"
            @disabled($currentIndex >= $this->answers->count() - 1)
            class="ui-btn-primary order-1 sm:order-2 disabled:opacity-40">
        Soal Berikutnya
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
</div>
