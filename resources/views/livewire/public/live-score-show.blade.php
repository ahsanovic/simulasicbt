<div wire:poll.10s="refreshBoard" class="min-h-screen">
    <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 pr-16 sm:px-6 sm:pr-20">
            <div class="min-w-0">
                <a href="{{ route('public.livescore.index') }}" class="text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-300">← Semua Event</a>
                <h1 class="truncate text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">{{ $event?->name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $event?->exam?->title }}</p>
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
                @if ($showExamTypePicker)
                    <select wire:model.live="viewMode" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 focus:border-primary-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500">
                        <option value="skd">SKD</option>
                        <option value="skb">SKB</option>
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
            {{-- Every participant is listed (public transparency). Rows use the short .ls-* classes
                 (resources/css/app.css) and are keyed by attempt so a refresh only touches rows that changed. --}}
            <div class="space-y-2">
@foreach ($this->rows as $row)
<div wire:key="{{ $row['key'] }}" class="ls-row{{ $row['rank'] <= 3 ? ' ls-row-'.$row['rank'] : '' }}"><div class="ls-rank">{{ $row['rank'] }}</div><div class="ls-who"><p class="ls-name">{{ $row['name'] }}</p><p class="ls-meta">{{ $row['session'] }}@if ($category === 'skb')@if ($row['jabatan']) · {{ $row['jabatan'] }}@endif @elseif ($row['instansi']) · {{ $row['instansi'] }}@endif</p></div><div class="ls-scores"><div class="ls-done"><p class="ls-label">Dikerjakan</p><p class="ls-value">{{ $row['answered'] }}<span class="ls-of">/{{ $row['total'] }}</span></p></div><div class="ls-group">@if ($category === 'skb')<div class="ls-stat"><p class="ls-label">Benar</p><p class="ls-value">{{ $row['benar'] }}</p></div>@else<div class="ls-stat"><p class="ls-label">TWK</p><p class="ls-value">{{ $row['twk'] }}</p></div><div class="ls-stat"><p class="ls-label">TIU</p><p class="ls-value">{{ $row['tiu'] }}</p></div><div class="ls-stat"><p class="ls-label">TKP</p><p class="ls-value">{{ $row['tkp'] }}</p></div>@endif</div><div class="ls-sum"><p class="ls-label">Total</p><p class="ls-total">{{ $row['score'] }}</p></div><div class="ls-state">@if ($row['in_progress'])<span class="ls-pill ls-live">Ujian</span>@else<span class="ls-pill ls-end">Selesai</span>@endif</div></div></div>
@endforeach
            </div>
        @endif
    </main>
</div>
