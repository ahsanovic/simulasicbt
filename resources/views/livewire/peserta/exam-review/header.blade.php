<header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div class="min-w-0">
            <a href="{{ route('peserta.history') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-primary-600 transition hover:text-primary-700">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Kembali ke Riwayat
            </a>
            <h1 class="mt-1 truncate text-lg font-bold text-slate-900">{{ $attempt->exam->title }}</h1>
            <p class="text-sm text-slate-500">
                Kunci Jawaban dan Pembahasan · Soal <span class="font-semibold text-slate-800">{{ $currentIndex + 1 }}</span> dari {{ $this->answers->count() }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm">
                <div>
                    <span class="text-slate-500">Benar</span>
                    <span class="ml-1.5 font-bold text-emerald-600">{{ $this->reviewStats['correct'] }}</span>
                </div>
                <div class="border-l border-slate-200 pl-4">
                    <span class="text-slate-500">Salah</span>
                    <span class="ml-1.5 font-bold text-rose-600">{{ $this->reviewStats['incorrect'] }}</span>
                </div>
                <div class="border-l border-slate-200 pl-4">
                    <span class="text-slate-500">Kosong</span>
                    <span class="ml-1.5 font-bold text-slate-500">{{ $this->reviewStats['unanswered'] }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-center">
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600">Skor Total</p>
                <p class="text-xl font-bold tabular-nums text-primary-700">{{ format_exam_score($attempt->total_score) }}</p>
            </div>
        </div>
    </div>
</header>
