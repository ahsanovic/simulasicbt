@if ($this->currentAnswer)
    <div class="ui-card p-6 sm:p-8">
        <div class="mb-5 flex flex-wrap items-center gap-2">
            @php $code = $this->currentAnswer->question->subject->code->value; @endphp
            <x-peserta.exam-question-badges :question="$this->currentAnswer->question" />
            @if ($code === 'tiu')
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
            {!! html_for_display($this->currentAnswer->question->content) !!}
        </div>

        <div class="space-y-3">
            @foreach ($this->currentAnswer->question->options as $option)
                @php $isEliminated = in_array($option->id, $this->currentEliminatedOptionIds, true); @endphp
                <label @class([
                    'flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 transition',
                    'border-primary-500 bg-primary-50/50 ring-4 ring-primary-500/10' => $selectedOptionId === $option->id && ! $isEliminated,
                    'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50' => $selectedOptionId !== $option->id && ! $isEliminated,
                    'border-slate-200 bg-slate-100 opacity-40 pointer-events-none' => $isEliminated,
                ])>
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        'bg-primary-600 text-white' => $selectedOptionId === $option->id && ! $isEliminated,
                        'bg-slate-100 text-slate-600' => $selectedOptionId !== $option->id || $isEliminated,
                    ])>{{ $option->label }}</span>
                    <input type="radio"
                           name="option"
                           value="{{ $option->id }}"
                           wire:click="selectOption({{ $option->id }})"
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
