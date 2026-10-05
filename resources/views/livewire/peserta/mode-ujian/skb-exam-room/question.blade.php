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
                {{-- Picking is browser-only (deferred wire:model): instant highlight, no request. --}}
                <label wire:key="opt-{{ $option->id }}" class="exam-option">
                    <span class="exam-option-letter">{{ $option->label }}</span>
                    <input type="radio"
                           name="option"
                           value="{{ $option->id }}"
                           wire:model="selectedOptionId"
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
