@props([
    'attempt',
    'analysis',
    'pending' => false,
])

@php
    $hasReport = filled($attempt->psychology_report) && $attempt->psychology_report_status === 'completed';
    $hasTelemetry = $analysis['has_data'] ?? false;
    $showCard = $pending || $hasReport || $hasTelemetry;
@endphp

@if ($showCard)
    <div
        @class([
            'ui-card overflow-hidden border-2',
            'border-violet-200' => $hasReport || $pending,
            'border-dashed border-slate-200' => ! $hasReport && ! $pending && $hasTelemetry,
        ])
        @if ($pending) wire:poll.5s="refreshPsychologyReport" @endif
    >
        <div class="relative overflow-hidden bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-700 px-5 py-5 text-white sm:px-6">
            <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 text-xl" aria-hidden="true">
                    🧠
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold tracking-tight sm:text-lg">Rapor Psikologi Ujian AI</h2>
                    <p class="mt-0.5 text-sm text-white/85">Analisis pola panik &amp; stres saat ujian</p>
                </div>
            </div>
        </div>

        <div class="space-y-4 p-5 sm:p-6">
            @if ($pending)
                <div class="flex items-center gap-3 rounded-2xl border border-violet-100 bg-violet-50 px-4 py-4">
                    <svg class="h-5 w-5 shrink-0 animate-spin text-violet-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-violet-900">AI sedang menganalisis pola perilakumu...</p>
                        <p class="mt-0.5 text-xs text-violet-700">Halaman akan diperbarui otomatis saat rapor siap.</p>
                    </div>
                </div>
            @elseif ($hasReport)
                <div class="prose prose-sm max-w-none text-slate-700 prose-b:font-bold prose-b:text-violet-900">
                    {!! format_psychology_report($attempt->psychology_report) !!}
                </div>
            @elseif ($hasTelemetry)
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-slate-800">
                        Halo {{ auth()->user()->name }}! Berikut ringkasan pola perilaku di {{ $analysis['panic_window_minutes'] }} menit terakhir ujianmu:
                    </p>

                    @if (($analysis['total_changes_in_panic_window'] ?? 0) > 0)
                        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <span class="font-bold">Deteksi Pola:</span>
                            Kamu mengubah {{ $analysis['total_changes_in_panic_window'] }} jawaban saat waktu mulai menipis.
                            @if (($analysis['correct_to_wrong_in_panic_window'] ?? 0) > 0)
                                Sayangnya, {{ $analysis['correct_to_wrong_in_panic_window'] }} di antaranya awalnya sudah benar lalu berubah menjadi salah.
                            @endif
                        </p>
                    @endif

                    @if (($analysis['fast_skim_in_panic_window'] ?? 0) > 0)
                        <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            <span class="font-bold">Rata-rata Waktu:</span>
                            Ada {{ $analysis['fast_skim_in_panic_window'] }} soal dikerjakan kurang dari 10 detik di bagian akhir
                            @if (($analysis['average_seconds_in_panic_window'] ?? 0) > 0)
                                (rata-rata {{ $analysis['average_seconds_in_panic_window'] }} detik/soal)
                            @endif
                            — kemungkinan membaca soal secara melompat tanpa memahami konteks.
                        </p>
                    @endif

                    @if (($analysis['total_changes_in_panic_window'] ?? 0) === 0 && ($analysis['fast_skim_in_panic_window'] ?? 0) === 0)
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Pola panik tidak terdeteksi secara signifikan. Pertahankan ketenanganmu di ujian berikutnya!
                        </p>
                    @endif
                </div>
            @endif

            @if ($attempt->psychology_report_status === 'failed')
                <p class="text-xs text-rose-600">Rapor AI gagal dibuat. Ringkasan statistik di atas tetap tersedia.</p>
            @endif
        </div>
    </div>
@endif
