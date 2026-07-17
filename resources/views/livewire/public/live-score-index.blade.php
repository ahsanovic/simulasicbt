<div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
    <div class="mb-10 text-center">
        <div class="mb-4 flex items-center justify-center gap-4">
            <img src="{{ asset('images/jatimlogo.png') }}" alt="Pemprov Jatim" class="h-12 w-auto object-contain">
            <img src="{{ asset('images/bkdlogo.png') }}" alt="BKD Jatim" class="h-12 w-auto object-contain">
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Livescore Event</h1>
        <p class="mt-2 text-slate-400">Pilih event untuk melihat papan skor langsung.</p>
    </div>

    @if ($events->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 p-12 text-center">
            <p class="text-slate-400">Belum ada event dengan livescore publik yang aktif.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('public.livescore.show', $event) }}"
                   class="group flex flex-col rounded-2xl border border-slate-800 bg-slate-900/70 p-6 transition hover:border-sky-500/60 hover:bg-slate-800/70">
                    <div class="flex-1">
                        <h2 class="text-lg font-bold text-white group-hover:text-sky-300">{{ $event->name }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $event->exam?->title }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-slate-800 px-2.5 py-1 font-semibold text-slate-300">{{ $event->sessions_count }} sesi</span>
                            <span class="rounded-full bg-slate-800 px-2.5 py-1 font-semibold text-slate-300">{{ $event->attempts_count }} peserta</span>
                        </div>
                    </div>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-sky-400">
                        Lihat Livescore →
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
