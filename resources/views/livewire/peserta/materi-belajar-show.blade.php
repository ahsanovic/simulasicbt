<div class="min-h-screen bg-gradient-to-b from-slate-50 to-emerald-50/30">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
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
        </article>
    </main>
</div>
