@if ($this->currentQuestion)
    <div class="ui-card p-6 sm:p-8">
        <div class="mb-5 flex flex-wrap items-center gap-2">
            @php $code = $this->currentQuestion->subject->code->value; @endphp
            <x-peserta.exam-question-badges :question="$this->currentQuestion" />
            {{-- Scratchpad is not loaded in mode ujian (it would clash with the anti-cheat guard), so no button there either. --}}
            @if ($code === 'tiu' && ! $isModeUjian)
                <button type="button"
                        x-data
                        x-on:click="$dispatch('open-scratchpad')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200 transition hover:bg-amber-100">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    Coretan
                </button>
            @endif
            @if($this->answerStates[$currentIndex]['is_marked'] ?? false)
                <span class="ui-badge bg-amber-100 text-amber-800">★ Ditandai</span>
            @endif
        </div>

        <div class="prose-exam mb-8 text-base">
            {!! html_for_display($this->currentQuestion->content) !!}
        </div>

        <div class="space-y-3">
            @foreach ($this->currentQuestion->options as $option)
                @php $isEliminated = in_array($option->id, $this->currentEliminatedOptionIds, true); @endphp
                {{-- Picking is browser-only (deferred wire:model): instant highlight, no request. --}}
                <label wire:key="opt-{{ $option->id }}" @class(['exam-option', 'exam-option-eliminated' => $isEliminated])>
                    <span class="exam-option-letter">{{ $option->label }}</span>
                    <input type="radio"
                           name="option"
                           value="{{ $option->id }}"
                           wire:model="selectedOptionId"
                           @checked($selectedOptionId === $option->id)
                           @disabled($isEliminated)
                           class="sr-only">
                    <span @class([
                        'flex-1 pt-1 text-sm leading-relaxed text-slate-800',
                        'line-through' => $isEliminated,
                    ])>
                        @if ($option->isImage())
                            <img src="{{ $option->imageUrl() }}" alt="Pilihan {{ $option->label }}" class="max-h-48 max-w-full rounded-lg object-contain">
                        @else
                            {!! $option->content !!}
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif
