@if ($this->currentAnswer)
    <div class="ui-card p-6 sm:p-8">
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <span class="ui-badge bg-slate-100 text-slate-700">Soal {{ $currentIndex + 1 }}</span>
            @if ($answerStates[$currentIndex]['is_marked'] ?? false)
                <span class="ui-badge bg-amber-100 text-amber-800">★ Ditandai</span>
            @endif
        </div>

        <div class="prose-exam mb-8 text-base">
            {!! html_for_display($this->currentAnswer->question->content) !!}
        </div>

        <div class="space-y-3">
            @foreach ($this->currentAnswer->question->options as $option)
                <label @class([
                    'flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 transition',
                    'border-primary-500 bg-primary-50/50 ring-4 ring-primary-500/10' => $selectedOptionId === $option->id,
                    'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50' => $selectedOptionId !== $option->id,
                ])>
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        'bg-primary-600 text-white' => $selectedOptionId === $option->id,
                        'bg-slate-100 text-slate-600' => $selectedOptionId !== $option->id,
                    ])>{{ $option->label }}</span>
                    <input type="radio"
                           name="option"
                           value="{{ $option->id }}"
                           wire:click="selectOption({{ $option->id }})"
                           @checked($selectedOptionId === $option->id)
                           class="sr-only">
                    <span class="flex-1 pt-1 text-sm leading-relaxed text-slate-800">
                        @if ($option->content_type === \App\Enums\QuestionOptionContentType::Image)
                            <img src="{{ storage_asset($option->image_path) }}" alt="Pilihan {{ $option->label }}" class="max-h-48 max-w-full rounded-lg object-contain">
                        @else
                            {!! $option->content !!}
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif
