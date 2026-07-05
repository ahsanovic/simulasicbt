@if ($repeatExam || $needsRefresh)
    <div @class([
        'space-y-2',
        'shrink-0 border-t border-primary-100/80 bg-gradient-to-t from-white/95 to-white/70 p-4 pt-3' => ! ($inline ?? false),
        'flex flex-col gap-3 sm:flex-row sm:justify-end' => $inline ?? false,
    ])>
        @if ($repeatExam)
            <button wire:click="repeatSimulation"
                    wire:loading.attr="disabled"
                    wire:target="repeatSimulation"
                    @class([
                        'ui-btn-success h-10 whitespace-nowrap',
                        'w-full' => ($fullWidth ?? true) && ! ($inline ?? false),
                        'sm:min-w-[200px]' => $inline ?? false,
                    ])>
                <span wire:loading wire:target="repeatSimulation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                <span wire:loading.remove wire:target="repeatSimulation" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Ulangi Simulasi →
                </span>
                <span wire:loading wire:target="repeatSimulation">Memulai...</span>
            </button>
        @endif

        @if ($needsRefresh)
            <button wire:click="generateRecommendation"
                    wire:loading.attr="disabled"
                    wire:target="generateRecommendation"
                    @class([
                        'ui-btn-primary h-10 whitespace-nowrap',
                        'w-full' => ($fullWidth ?? true) && ! ($inline ?? false),
                        'sm:min-w-[240px]' => $inline ?? false,
                    ])>
                <span wire:loading wire:target="generateRecommendation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                <span wire:loading.remove wire:target="generateRecommendation">Perbarui Rekomendasi AI ✨</span>
                <span wire:loading wire:target="generateRecommendation">Memperbarui...</span>
            </button>
        @endif
    </div>
@endif
