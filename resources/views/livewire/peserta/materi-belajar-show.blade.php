<div class="min-h-screen bg-gradient-to-b from-slate-50 to-emerald-50/30">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-6">
            <a
                href="{{ route('peserta.materi.index', ['kategori' => $material->subject->code->value]) }}"
                wire:navigate
                class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 transition hover:text-emerald-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali ke daftar materi
            </a>
        </div>

        <article class="ui-card overflow-hidden">
            <header class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-teal-50 px-6 py-5 sm:px-8">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1">{{ $material->subject->code->label() }}</span>
                    @if ($material->materialGroup)
                        <span class="text-emerald-600">{{ $material->materialGroup->name }}</span>
                    @endif
                </div>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $material->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Cheat-Sheet Kilat · perkiraan baca &lt; 2 menit
                    @if ($material->cheatSheet?->generated_at)
                        · diperbarui {{ $material->cheatSheet->generated_at->translatedFormat('d M Y') }}
                    @endif
                </p>
            </header>

            <div class="prose-cheat-sheet px-6 py-6 sm:px-8 sm:py-8">
                {!! format_cheat_sheet_content($material->cheatSheet?->content) !!}
            </div>

            <footer class="border-t border-slate-100 bg-slate-50/80 px-6 py-5 sm:px-8">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-5">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Tandai selesai baca</p>
                        <p class="text-xs text-slate-500">Hitung sebagai aktivitas harian untuk streak konsistensi & pengali XP.</p>
                    </div>
                    @if ($this->isCheatSheetCompletedToday)
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-100 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                            ✅ Selesai hari ini
                        </span>
                    @else
                        <button type="button"
                                wire:click="markCheatSheetComplete"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            <span wire:loading.remove wire:target="markCheatSheetComplete">📖 Tandai Selesai Baca</span>
                            <span wire:loading wire:target="markCheatSheetComplete">Menyimpan...</span>
                        </button>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-5">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Simpan ke Kartu Sakti</p>
                        <p class="text-xs text-slate-500">Review materi ini dengan spaced repetition agar tidak lupa.</p>
                    </div>
                    @if ($this->isSavedToFlashcard)
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-100 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                            ✅ Sudah di Kartu Sakti
                        </span>
                    @else
                        <button type="button"
                                wire:click="saveToFlashcard"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600">
                            <span wire:loading.remove wire:target="saveToFlashcard">⭐ Simpan ke Kartu Sakti</span>
                            <span wire:loading wire:target="saveToFlashcard">Menyimpan...</span>
                        </button>
                    @endif
                </div>
            </footer>
        </article>
    </main>
</div>
