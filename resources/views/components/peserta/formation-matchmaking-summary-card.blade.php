@props([
    'hasHistory' => false,
    'summary' => null,
    'variant' => 'full',
])

@if ($variant === 'compact')
    @php
        $zoneStyles = match (true) {
            ! $hasHistory => 'border-slate-200/70 bg-gradient-to-b from-white via-slate-50/50 to-slate-50/30',
            ($summary['zone'] ?? null) === 'safe' => 'border-emerald-200/70 bg-gradient-to-b from-white via-emerald-50/30 to-teal-50/20',
            ($summary['zone'] ?? null) === 'caution' => 'border-amber-200/70 bg-gradient-to-b from-white via-amber-50/30 to-orange-50/20',
            ($summary['zone'] ?? null) === 'risk' => 'border-rose-200/70 bg-gradient-to-b from-white via-rose-50/30 to-orange-50/20',
            default => 'border-teal-200/70 bg-gradient-to-b from-white via-teal-50/30 to-cyan-50/20',
        };
    @endphp

    <div class="ui-card relative flex h-full flex-col overflow-hidden {{ $zoneStyles }} shadow-lg shadow-primary-100/20">
        <div class="border-b border-inherit bg-gradient-to-r from-teal-600 via-primary-600 to-indigo-600 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                    <span class="text-sm" aria-hidden="true">🎯</span>
                </div>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-bold text-white">Simulasi Formasi</h3>
                    <p class="truncate text-[10px] font-medium text-primary-100/90">
                        @if (! $hasHistory)
                            Tersedia setelah simulasi
                        @elseif ($summary)
                            {{ $summary['formation_name'] }}
                        @else
                            Belum ada target jabatan
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col p-4">
            @if (! $hasHistory)
                <p class="flex-1 text-sm leading-relaxed text-slate-600">
                    Selesaikan simulasi pertama untuk membandingkan skor dengan pelamar jabatan impian.
                </p>
                <p class="mt-3 text-xs font-medium text-slate-400">Kerjakan ujian di bawah untuk membuka fitur ini.</p>
            @elseif ($summary && $summary['insufficient_data'])
                <p class="flex-1 text-sm text-slate-600">{{ $summary['message'] }}</p>
            @elseif ($summary && $summary['rank'])
                <div class="flex-1">
                    <div class="rounded-xl bg-white/80 px-3 py-2.5 text-center ring-1 ring-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Peringkat</p>
                        <p class="mt-0.5 text-xl font-extrabold tabular-nums text-slate-900">#{{ $summary['rank'] }} <span class="text-sm font-semibold text-slate-500">/ {{ $summary['applicant_count'] }}</span></p>
                    </div>
                </div>
            @else
                <p class="flex-1 text-sm leading-relaxed text-slate-600">
                    Bandingkan skor terbaik Anda dengan pelamar jabatan impian di aplikasi ini.
                </p>
            @endif

            @if ($hasHistory)
                <a href="{{ route('peserta.simulasi-formasi') }}"
                   wire:navigate
                   class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary-300/40 transition hover:bg-primary-700">
                    {{ $summary ? 'Lihat detail' : 'Pilih target jabatan' }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endif
        </div>
    </div>
@elseif ($hasHistory)
    @if ($summary)
        @php
            $zoneStyles = match ($summary['zone'] ?? null) {
                'safe' => 'border-emerald-200/70 bg-gradient-to-b from-white via-emerald-50/30 to-teal-50/20',
                'caution' => 'border-amber-200/70 bg-gradient-to-b from-white via-amber-50/30 to-orange-50/20',
                'risk' => 'border-rose-200/70 bg-gradient-to-b from-white via-rose-50/30 to-orange-50/20',
                default => 'border-primary-200/60 bg-gradient-to-b from-white via-primary-50/20 to-indigo-50/30',
            };
        @endphp

        <div class="ui-card relative overflow-hidden {{ $zoneStyles }} shadow-lg shadow-primary-100/20">
            <div class="border-b border-inherit bg-gradient-to-r from-teal-600 via-primary-600 to-indigo-600 px-4 py-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                        <span class="text-sm" aria-hidden="true">🎯</span>
                    </div>
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-bold text-white">Simulasi Formasi</h3>
                        <p class="truncate text-[10px] font-medium text-primary-100/90">{{ $summary['formation_name'] }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3 p-4">
                @if ($summary['insufficient_data'])
                    <p class="text-sm text-slate-600">{{ $summary['message'] }}</p>
                @elseif ($summary['rank'])
                    <div class="rounded-xl bg-white/80 px-3 py-2.5 text-center ring-1 ring-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Peringkat</p>
                        <p class="mt-0.5 text-lg font-extrabold tabular-nums text-slate-900">#{{ $summary['rank'] }} <span class="text-sm font-semibold text-slate-500">/ {{ $summary['applicant_count'] }}</span></p>
                    </div>
                @endif

                <a href="{{ route('peserta.simulasi-formasi') }}"
                   wire:navigate
                   class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary-300/40 transition hover:bg-primary-700">
                    Lihat detail
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    @else
        <div class="ui-card relative overflow-hidden border-teal-200/70 bg-gradient-to-b from-white via-teal-50/30 to-cyan-50/20 shadow-lg shadow-teal-100/20">
            <div class="border-b border-teal-100/80 bg-gradient-to-r from-teal-600 via-primary-600 to-indigo-600 px-4 py-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                        <span class="text-sm" aria-hidden="true">🎯</span>
                    </div>
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-bold text-white">Simulasi Formasi</h3>
                        <p class="text-[10px] font-medium text-primary-100/90">Belum ada target jabatan</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <p class="text-sm leading-relaxed text-slate-600">
                    Bandingkan skor terbaik Anda dengan pelamar jabatan impian di aplikasi ini.
                </p>

                <a href="{{ route('peserta.simulasi-formasi') }}"
                   wire:navigate
                   class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-300/40 transition hover:bg-teal-700">
                    Pilih target jabatan
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif
@endif
