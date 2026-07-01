@if ($this->currentAnswer)
    @php
        $answer = $this->currentAnswer;
        $question = $answer->question;
        $subjectCode = $question->subject->code->value;
        $usesWeightedScoring = $question->usesWeightedScoring();
    @endphp

    <div class="ui-card p-6 sm:p-8">
        <div class="prose-exam mb-8 text-base">
            {!! $question->content !!}
        </div>

        <div class="space-y-3">
            @foreach ($question->options as $option)
                @php
                    $isSelected = $answer->selected_option_id === $option->id;
                    $isKeyAnswer = $question->isKeyOption($option);
                @endphp
                <div @class([
                    'relative flex items-start gap-4 rounded-2xl border-2 p-4 transition',
                    'border-primary-500 bg-primary-50/50 ring-4 ring-primary-500/10' => $isSelected && ! $isKeyAnswer,
                    'border-emerald-500 bg-emerald-50/50 ring-4 ring-emerald-500/10' => $isKeyAnswer && ! $isSelected,
                    'border-emerald-600 bg-emerald-50 ring-4 ring-emerald-500/20' => $isKeyAnswer && $isSelected,
                    'border-rose-400 bg-rose-50/40' => $isSelected && ! $isKeyAnswer && ! $usesWeightedScoring,
                    'border-slate-200 bg-white' => ! $isSelected && ! $isKeyAnswer,
                ])>
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        'bg-primary-600 text-white' => $isSelected && ! $isKeyAnswer,
                        'bg-emerald-600 text-white' => $isKeyAnswer,
                        'bg-slate-100 text-slate-600' => ! $isSelected && ! $isKeyAnswer,
                    ])>{{ $option->label }}</span>

                    <div class="min-w-0 flex-1 pt-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($isSelected)
                                <span class="ui-badge bg-primary-100 text-primary-700">Jawaban Anda</span>
                            @endif
                            @if ($isKeyAnswer)
                                <span class="ui-badge bg-emerald-100 text-emerald-700">
                                    {{ $usesWeightedScoring ? 'Bobot Tertinggi' : 'Kunci Jawaban' }}
                                </span>
                            @endif
                            @if ($usesWeightedScoring)
                                <span class="ui-badge bg-violet-50 text-violet-700">Bobot {{ $option->score_weight }}</span>
                            @endif
                        </div>

                        <div class="mt-2 text-sm leading-relaxed text-slate-800">
                            @if ($option->isImage())
                                <img src="{{ $option->imageUrl() }}" alt="Pilihan {{ $option->label }}" class="max-h-48 max-w-full rounded-lg object-contain">
                            @else
                                {!! $option->content !!}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($question->explanation)
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
                <div class="mb-2 flex items-center gap-2">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <h3 class="text-sm font-bold text-amber-900">Pembahasan</h3>
                </div>
                <div class="prose-exam text-sm text-amber-950/90">
                    {!! $question->explanation !!}
                </div>
            </div>
        @endif
    </div>
@endif
