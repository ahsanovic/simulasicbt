<aside class="ui-card h-fit p-5 xl:sticky xl:top-24">
    <h2 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Navigasi Soal</h2>
    <div class="grid grid-cols-5 gap-2 sm:grid-cols-6 xl:grid-cols-5">
        @foreach ($this->answers as $index => $answer)
            @php
                $isCurrent = $currentIndex === $index;
                $isMarked = $answer->is_marked;
                $isAnswered = (bool) $answer->selected_option_id;
            @endphp
            <button type="button"
                    wire:key="nav-{{ $answer->id }}-{{ (int) $isMarked }}-{{ (int) $isAnswered }}-{{ $currentIndex }}"
                    wire:click="goToQuestion({{ $index }})"
                    @class([
                        'relative flex h-10 items-center justify-center rounded-xl text-sm font-bold transition',
                        'bg-primary-600 text-white shadow-md shadow-primary-500/30 ring-2 ring-primary-300' => $isCurrent,
                        'bg-amber-400 text-amber-950 ring-2 ring-amber-500 hover:bg-amber-500' => ! $isCurrent && $isMarked,
                        'bg-emerald-500 text-white hover:bg-emerald-600' => ! $isCurrent && ! $isMarked && $isAnswered,
                        'bg-rose-500 text-white hover:bg-rose-600' => ! $isCurrent && ! $isMarked && ! $isAnswered,
                    ])>
                {{ $index + 1 }}
            </button>
        @endforeach
    </div>

    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-emerald-500"></span> Sudah dijawab</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-rose-500"></span> Belum dijawab</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-amber-400"></span> Ditandai</div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-primary-600"></span> Soal aktif</div>
    </div>
</aside>
