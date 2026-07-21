@props([
    'attempt',
    'analysis',
])

@php
    $hasData = ($analysis['has_data'] ?? false) === true;
    $isInsufficient = ($analysis['insufficient'] ?? false) === true;
@endphp

@if ($attempt->stress_test_enabled && ($hasData || $isInsufficient))
    <div @class([
        'ui-card overflow-hidden border-2',
        'border-rose-200' => $hasData,
        'border-dashed border-slate-300' => $isInsufficient,
    ])>
        <div @class([
            'relative overflow-hidden px-5 py-5 text-white sm:px-6',
            'bg-gradient-to-br from-rose-500 via-orange-500 to-amber-500' => $hasData,
            'bg-gradient-to-br from-slate-500 via-slate-600 to-slate-700' => $isInsufficient,
        ])>
            <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 text-xl" aria-hidden="true">
                        🧠
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold tracking-tight sm:text-lg">Skor Ketahanan Stres</h2>
                        <p class="mt-0.5 text-sm text-white/85">
                            @if ($hasData)
                                Tingkat ketahanan stres saat mode gangguan aktif
                            @else
                                Analisis belum tersedia — data simulasi belum memadai
                            @endif
                        </p>
                    </div>
                </div>
                @if ($hasData)
                    <div class="shrink-0 text-right">
                        <p class="text-3xl font-black tabular-nums">{{ $analysis['score'] }}%</p>
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/80">{{ $analysis['level_label'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4 p-5 sm:p-6">
            @if ($isInsufficient)
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700">
                    {{ $analysis['insight'] }}
                </p>
                @if (($analysis['total_answered'] ?? 0) > 0)
                    <p class="text-xs text-slate-500">
                        Saat ini: {{ $analysis['total_answered'] }} soal dijawab
                        @if (($analysis['stress_answered'] ?? 0) > 0)
                            · {{ $analysis['stress_answered'] }} di zona stres (15 menit terakhir)
                        @else
                            · belum ada jawaban di zona stres
                        @endif
                    </p>
                @endif
            @else
                @if (! empty($analysis['segments']))
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($analysis['segments'] as $segment)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $segment['label'] }}</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $segment['accuracy'] }}%</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $segment['answered'] }} soal dijawab</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm leading-relaxed text-rose-900">
                    <span class="font-bold">Tingkat Ketahanan Stres: {{ $analysis['score'] }}% ({{ $analysis['level_label'] }})</span><br>
                    {{ $analysis['insight'] }}
                </p>

                @if (($analysis['red_zone_triggers'] ?? 0) > 0)
                    <p class="text-xs text-slate-500">
                        Red-zone terpicu <span class="font-semibold text-slate-700">{{ $analysis['red_zone_triggers'] }}×</span>
                        @if (! empty($analysis['red_zone_questions']))
                            pada soal
                            @foreach ($analysis['red_zone_questions'] as $questionNumber)
                                <span class="font-semibold text-rose-600">{{ $questionNumber }}</span>@if (! $loop->last), @endif
                            @endforeach.
                        @endif
                    </p>
                @endif
            @endif
        </div>
    </div>
@endif
