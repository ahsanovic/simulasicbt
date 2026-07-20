<div wire:poll.5s class="min-h-screen">
    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 pr-16 sm:px-6 sm:pr-20">
            <div class="min-w-0">
                <a href="{{ route('public.livescore.index') }}" class="text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-300">← Semua Event</a>
                <h1 class="truncate text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $event->name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $event->exam?->title }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($this->sessions->count() > 1)
                    <select wire:model.live="sessionId" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500">
                        <option value="">Semua Sesi</option>
                        @foreach ($this->sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
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
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center dark:border-slate-700 dark:bg-slate-900/50">
                <p class="text-lg text-slate-500 dark:text-slate-400">Belum ada peserta pada papan skor ini.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($this->rows as $row)
                    @php
                        $rankStyle = match ($row['rank']) {
                            1 => 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10',
                            2 => 'border-slate-300 bg-slate-100 dark:border-slate-400/40 dark:bg-slate-400/10',
                            3 => 'border-orange-300 bg-orange-50 dark:border-orange-600/40 dark:bg-orange-500/10',
                            default => 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/60',
                        };
                        $rankBadge = match ($row['rank']) {
                            1 => 'bg-amber-400 text-amber-950',
                            2 => 'bg-slate-300 text-slate-800',
                            3 => 'bg-orange-500 text-orange-950',
                            default => 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                        };
                    @endphp
                    <div wire:key="rank-{{ $row['rank'] }}-{{ $row['name'] }}" class="flex items-center gap-4 rounded-2xl border px-4 py-3 shadow-sm sm:px-5 dark:shadow-none {{ $rankStyle }}">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg font-black {{ $rankBadge }}">
                            {{ $row['rank'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold text-slate-900 dark:text-white sm:text-xl">{{ $row['name'] }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                @if($row['session']){{ $row['session'] }}@endif
                                @if($row['instansi']) · {{ $row['instansi'] }}@endif
                            </p>
                            {{-- Layar sempit: skor per tes tetap terlihat sebagai ringkasan --}}
                            <p class="mt-0.5 text-xs font-semibold tabular-nums text-slate-600 md:hidden dark:text-slate-300">
                                TWK {{ $row['twk'] }} · TIU {{ $row['tiu'] }} · TKP {{ $row['tkp'] }}
                            </p>
                        </div>
                        <div class="hidden shrink-0 text-center sm:block">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Dikerjakan</p>
                            <p class="text-base font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $row['answered'] }}<span class="text-slate-400 dark:text-slate-500">/{{ $row['total'] }}</span></p>
                        </div>
                        <div class="hidden shrink-0 items-center gap-3 md:flex">
                            @foreach (['TWK' => $row['twk'], 'TIU' => $row['tiu'], 'TKP' => $row['tkp']] as $label => $value)
                                <div class="w-12 text-center">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $label }}</p>
                                    <p class="text-base font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="shrink-0 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total</p>
                            <p class="text-2xl font-black tabular-nums text-slate-900 dark:text-white sm:text-3xl">{{ $row['score'] }}</p>
                        </div>
                        <div class="w-24 shrink-0 text-right">
                            @if($row['in_progress'])
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span> Ujian
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span> Selesai
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</div>
