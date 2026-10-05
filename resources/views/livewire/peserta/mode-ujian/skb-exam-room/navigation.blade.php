<aside class="ui-card h-fit p-5 xl:sticky xl:top-24">
    <h2 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Navigasi Soal</h2>
    <div class="grid grid-cols-5 gap-2 sm:grid-cols-6 xl:grid-cols-5">
        @foreach ($this->answers as $index => $state)
            @php
                $isCurrent = $currentIndex === $index;
                $isMarked = $state['is_marked'];
                $isAnswered = (bool) $state['selected_option_id'];
            @endphp
            {{-- Short class names (see app.css .qnav): this grid is re-sent on every action. --}}
            <button type="button" wire:key="n{{ $index }}" wire:click="goToQuestion({{ $index }})"
                    class="qnav {{ $isCurrent ? 'qnav-current' : ($isMarked ? 'qnav-marked' : ($isAnswered ? 'qnav-answered' : 'qnav-empty')) }}">
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
