<aside class="ui-card h-fit p-5 xl:sticky xl:top-24">
    <h2 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Navigasi Soal</h2>
    <div class="grid grid-cols-5 gap-2 sm:grid-cols-6 xl:grid-cols-5">
        @foreach ($this->answers as $index => $answer)
            @php
                $isCurrent = $currentIndex === $index;
                $outcome = $answer->reviewOutcome();
                $isPositive = $outcome->isPositive();
                $isUnanswered = $outcome->value === 'unanswered';
            @endphp
            <button type="button"
                    wire:key="review-nav-{{ $answer->id }}-{{ $currentIndex }}"
                    wire:click="goToQuestion({{ $index }})"
                    @class([
                        'relative flex h-10 items-center justify-center rounded-xl text-sm font-bold transition',
                        'bg-primary-600 text-white shadow-md shadow-primary-500/30 ring-2 ring-primary-300' => $isCurrent,
                        'bg-emerald-500 text-white hover:bg-emerald-600' => ! $isCurrent && $isPositive,
                        'bg-rose-500 text-white hover:bg-rose-600' => ! $isCurrent && ! $isPositive && ! $isUnanswered,
                        'bg-slate-300 text-slate-700 hover:bg-slate-400' => ! $isCurrent && $isUnanswered,
                    ])>
                {{ $index + 1 }}
            </button>
        @endforeach
    </div>

    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-emerald-500"></span> Benar / bobot tertinggi</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-rose-500"></span> Salah / bobot lebih rendah</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-slate-300"></span> Tidak dijawab</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-primary-600"></span> Soal aktif</div>
    </div>
</aside>
