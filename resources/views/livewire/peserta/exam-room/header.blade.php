<header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Sedang Ujian</p>
            <h1 class="truncate text-lg font-bold text-slate-900">{{ $examTitle }}</h1>
            <p class="text-sm text-slate-500">Soal <span class="font-semibold text-slate-800">{{ $currentIndex + 1 }}</span> dari {{ $this->answers->count() }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 sm:gap-4">
            <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2.5" wire:ignore>
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-100">
                    <svg class="h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div x-data="examTimer({{ max(0, $this->remainingSeconds) }})">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Sisa Waktu</p>
                    <p class="text-xl font-bold tabular-nums text-rose-700" x-text="formattedTime"></p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm">
                    <div>
                        <span class="text-slate-500">Dijawab</span>
                        <span class="ml-1.5 font-bold text-emerald-600">{{ $this->answeredCount }}</span>
                    </div>
                    <div class="border-l border-slate-200 pl-4">
                        <span class="text-slate-500">Belum</span>
                        <span class="ml-1.5 font-bold text-amber-600">{{ $this->unansweredCount }}</span>
                    </div>
                </div>

                <button type="button"
                        wire:click="submitExam"
                        wire:confirm="Selesaikan simulasi ini? Skor akan disimpan dan Anda dapat mengulang lagi nanti."
                        class="ui-btn-danger shrink-0">
                    Selesai Ujian
                </button>
            </div>
        </div>
    </div>
</header>
