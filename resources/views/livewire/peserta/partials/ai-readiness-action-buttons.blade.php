@php
    $aiGeneration = $aiGeneration ?? ['status' => 'no_simulation', 'message' => '', 'existing_plan' => null];
    $showAiPlanSection = ($repeatExam ?? false)
        || ($needsRefresh ?? false)
        || in_array($aiGeneration['status'] ?? '', ['available', 'already_generated'], true);
@endphp

@if ($showAiPlanSection)
    <div @class([
        'space-y-2',
        'shrink-0 border-t border-primary-100/80 bg-gradient-to-t from-white/95 to-white/70 p-4 pt-3' => ! ($inline ?? false),
        'flex flex-col gap-3 sm:flex-row sm:justify-end' => $inline ?? false,
    ])>
        @if (($aiGeneration['status'] ?? '') === 'available')
            <button wire:click="generatePlanFromEvaluation"
                    wire:loading.attr="disabled"
                    wire:target="generatePlanFromEvaluation"
                    @class([
                        'ui-btn-secondary h-auto min-h-10 whitespace-nowrap border-blue-200 bg-blue-50 py-2.5 text-blue-700 hover:bg-blue-100',
                        'w-full' => ($fullWidth ?? true) && ! ($inline ?? false),
                        'sm:min-w-[260px]' => $inline ?? false,
                    ])>
                <span wire:loading wire:target="generatePlanFromEvaluation" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-blue-300 border-t-blue-700" aria-hidden="true"></span>
                <span wire:loading.remove wire:target="generatePlanFromEvaluation" class="inline-flex flex-col items-start gap-0.5 text-left">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Buat Rencana Otomatis dari Hasil Evaluasi
                    </span>
                    <span class="pl-6 text-[11px] font-medium opacity-80">{{ $aiGeneration['message'] }}</span>
                </span>
                <span wire:loading wire:target="generatePlanFromEvaluation">Membuat...</span>
            </button>
        @elseif (($aiGeneration['status'] ?? '') === 'already_generated' && ($aiGeneration['existing_plan'] ?? null))
            <a href="{{ route('peserta.rencana-belajar.index', ['plan' => $aiGeneration['existing_plan']->id]) }}"
               wire:navigate
               @class([
                   'inline-flex min-h-10 flex-col items-start justify-center gap-0.5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100',
                   'w-full' => ($fullWidth ?? true) && ! ($inline ?? false),
                   'sm:min-w-[260px]' => $inline ?? false,
               ])>
                <span class="inline-flex items-center gap-2 font-bold">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Buka Rencana dari Hasil Evaluasi
                </span>
                <span class="pl-6 text-[11px] font-medium text-blue-600/80">{{ $aiGeneration['message'] }}</span>
            </a>
        @endif

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
