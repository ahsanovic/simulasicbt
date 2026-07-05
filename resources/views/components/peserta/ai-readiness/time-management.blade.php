@props([
    'timeStats' => [],
    'sectionId' => 'readiness-section-time',
])

@if (($timeStats['has_data'] ?? false))
    <div id="{{ $sectionId }}" class="scroll-mt-4 space-y-3 rounded-xl border border-orange-100 bg-orange-50/40 p-3.5 sm:p-5">
        <p class="text-[11px] font-bold uppercase tracking-wider text-orange-700">⏱️ Analisis Manajemen Waktu</p>
        <p class="text-xs text-slate-600 sm:text-sm">
            Rata-rata kecepatan per pilar dari {{ $timeStats['total_exams_with_data'] }} simulasi (batas aman: {{ $timeStats['safe_seconds_per_question'] }} detik/soal)
        </p>
        <div @class([
            'grid gap-2',
            'sm:grid-cols-3 sm:gap-4' => count($timeStats['average_seconds_by_pillar'] ?? []) >= 3,
        ])>
            @foreach ($timeStats['average_seconds_by_pillar'] as $code => $average)
                @php
                    $safeSeconds = $timeStats['safe_seconds_per_question'];
                    $percent = $safeSeconds > 0 ? min(100, (int) round(($average / $safeSeconds) * 100)) : 0;
                    $isSlow = $average > $safeSeconds;
                @endphp
                <div wire:key="time-pillar-{{ $code }}" class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] sm:text-xs">
                        <span class="font-semibold text-slate-700">{{ strtoupper($code) }}</span>
                        <span @class([
                            'font-bold tabular-nums',
                            'text-rose-600' => $isSlow,
                            'text-emerald-600' => ! $isSlow,
                        ])>{{ $average }} dtk/soal</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-white">
                        <div @class([
                            'h-full rounded-full transition-all duration-500',
                            'bg-rose-500' => $isSlow,
                            'bg-emerald-500' => ! $isSlow,
                        ]) style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($timeStats['early_phase_average'] && $timeStats['late_phase_average'])
            <p class="text-xs leading-relaxed text-slate-600 sm:text-sm">
                Ritme awal (25% soal pertama): <strong>{{ $timeStats['early_phase_average'] }} dtk/soal</strong>
                · Ritme akhir (25% terakhir): <strong>{{ $timeStats['late_phase_average'] }} dtk/soal</strong>
            </p>
        @endif
    </div>
@endif
