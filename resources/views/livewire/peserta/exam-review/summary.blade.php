@if ($this->currentAnswer)
    @php
        $outcome = $this->currentAnswer->reviewOutcome();
        $question = $this->currentAnswer->question;
    @endphp

    @if (! $question)
        <div class="ui-card flex flex-wrap items-center gap-3 p-4">
            <span class="ui-badge bg-amber-100 text-amber-800">Soal tidak tersedia</span>
        </div>
    @else
        @php
            $subjectCode = $question->subject->code->value;
        @endphp

    <div class="ui-card flex flex-wrap items-center gap-3 p-4">
        <span @class([
            'ui-badge',
            'bg-blue-100 text-blue-700' => $subjectCode === 'twk',
            'bg-amber-100 text-amber-700' => $subjectCode === 'tiu',
            'bg-violet-100 text-violet-700' => $subjectCode === 'tkp',
        ])>{{ $question->subject->code->label() }}</span>

        <span @class([
            'ui-badge',
            'bg-emerald-100 text-emerald-700' => $outcome->isPositive(),
            'bg-rose-100 text-rose-700' => ! $outcome->isPositive() && $outcome->value !== 'unanswered',
            'bg-slate-100 text-slate-600' => $outcome->value === 'unanswered',
        ])>{{ $outcome->label() }}</span>

        @if ($this->currentAnswer->selectedOption)
            <span class="text-sm text-slate-500">
                Poin diperoleh: <span class="font-bold text-slate-800">{{ $this->currentAnswer->earnedPoints() }}</span>
            </span>
        @endif

        @if ($this->currentQuestionDurationSeconds > 0)
            @php
                $durationStatus = $this->currentQuestionDurationStatus;
            @endphp
            <span class="text-sm text-slate-500">
                Waktu pengerjaan di nomor ini:
                <span class="font-bold tabular-nums text-slate-800">{{ format_question_duration($this->currentQuestionDurationSeconds) }}</span> menit
                @if ($durationStatus)
                    <span @class([
                        'font-semibold',
                        'text-rose-600' => $durationStatus['color'] === 'rose',
                        'text-amber-600' => $durationStatus['color'] === 'amber',
                        'text-emerald-600' => $durationStatus['color'] === 'emerald',
                        'text-slate-500' => $durationStatus['color'] === 'slate',
                    ])>({{ $durationStatus['label'] }})</span>
                @endif
            </span>
        @endif
    </div>
    @endif
@endif
