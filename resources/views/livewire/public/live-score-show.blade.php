<div wire:poll.5s class="min-h-screen">
    <header class="sticky top-0 z-10 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="min-w-0">
                <a href="{{ route('public.livescore.index') }}" class="text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300">← Semua Event</a>
                <h1 class="truncate text-2xl font-bold text-white sm:text-3xl">{{ $event->name }}</h1>
                <p class="text-sm text-slate-400">{{ $event->exam?->title }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($this->sessions->count() > 1)
                    <select wire:model.live="sessionId" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-100 focus:border-sky-500 focus:outline-none">
                        <option value="">Semua Sesi</option>
                        @foreach ($this->sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-400">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-rose-500"></span>
                    </span>
                    LIVE
                </span>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        @if (count($this->rows) === 0)
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 p-16 text-center">
                <p class="text-lg text-slate-400">Belum ada peserta pada papan skor ini.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($this->rows as $row)
                    @php
                        $rankStyle = match ($row['rank']) {
                            1 => 'bg-gradient-to-r from-amber-500/20 to-transparent border-amber-500/40',
                            2 => 'bg-gradient-to-r from-slate-400/15 to-transparent border-slate-400/40',
                            3 => 'bg-gradient-to-r from-orange-600/15 to-transparent border-orange-600/40',
                            default => 'bg-slate-900/60 border-slate-800',
                        };
                        $rankBadge = match ($row['rank']) {
                            1 => 'bg-amber-400 text-amber-950',
                            2 => 'bg-slate-300 text-slate-800',
                            3 => 'bg-orange-500 text-orange-950',
                            default => 'bg-slate-800 text-slate-300',
                        };
                    @endphp
                    <div wire:key="rank-{{ $row['rank'] }}-{{ $row['name'] }}" class="flex items-center gap-4 rounded-2xl border px-4 py-3 sm:px-5 {{ $rankStyle }}">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg font-black {{ $rankBadge }}">
                            {{ $row['rank'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold text-white sm:text-xl">{{ $row['name'] }}</p>
                            <p class="truncate text-xs text-slate-400">
                                @if($row['session']){{ $row['session'] }}@endif
                                @if($row['instansi']) · {{ $row['instansi'] }}@endif
                            </p>
                        </div>
                        <div class="hidden shrink-0 text-center sm:block">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Dikerjakan</p>
                            <p class="text-base font-bold text-slate-200 tabular-nums">{{ $row['answered'] }}<span class="text-slate-500">/{{ $row['total'] }}</span></p>
                        </div>
                        <div class="shrink-0 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Skor</p>
                            <p class="text-2xl font-black tabular-nums text-white sm:text-3xl">{{ $row['score'] }}</p>
                        </div>
                        <div class="w-24 shrink-0 text-right">
                            @if($row['in_progress'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span> Ujian
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Selesai
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</div>
