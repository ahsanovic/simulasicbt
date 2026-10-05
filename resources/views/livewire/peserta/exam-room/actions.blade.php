<div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" x-data>
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
        {{-- Enabled from the browser-side pick; locked while the request runs so it can't be sent twice. --}}
        <button type="button"
                wire:click="next"
                wire:loading.attr="disabled"
                x-bind:disabled="! $wire.selectedOptionId"
                class="ui-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
            <span wire:loading.remove wire:target="next">Simpan &amp; Lanjutkan</span>
            <span wire:loading wire:target="next">Menyimpan…</span>
            <svg wire:loading.remove wire:target="next" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <svg wire:loading wire:target="next" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        </button>
    </div>
</div>
