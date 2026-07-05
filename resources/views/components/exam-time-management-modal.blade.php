@props([
    'analysis',
])

@php
    $maxSeconds = collect($analysis['longest_questions'])->max('seconds') ?: 1;
    $safeSeconds = $analysis['safe_seconds_per_question'];
@endphp

<div
    class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    role="dialog"
    aria-modal="true"
    aria-labelledby="time-management-title"
    x-data
    x-on:keydown.escape.window="$wire.closeTimeManagementModal()"
>
    <div
        class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"
        wire:click="closeTimeManagementModal"
    ></div>

    <div class="relative max-h-[90dvh] w-full max-w-2xl overflow-hidden overflow-y-auto rounded-3xl bg-white shadow-2xl shadow-slate-900/30">
        <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-500 to-rose-600 px-6 pb-6 pt-6 text-white sm:px-8">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
            <button
                type="button"
                wire:click="closeTimeManagementModal"
                class="absolute right-4 top-4 rounded-xl p-2 text-white/80 transition hover:bg-white/15 hover:text-white"
                aria-label="Tutup"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                    <span class="text-xl" aria-hidden="true">⏱️</span>
                </div>
                <div>
                    <h2 id="time-management-title" class="text-lg font-bold tracking-tight">Analisis Manajemen Waktu</h2>
                    <p class="text-sm text-white/85">Pola kecepatan berpikir pada simulasi ini</p>
                </div>
            </div>
        </div>

        <div class="space-y-6 p-6 sm:p-8">
            @if (! $analysis['has_data'])
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                    <p class="text-sm font-semibold text-slate-700">Data durasi belum tersedia</p>
                    <p class="mt-2 text-xs text-slate-500">Simulasi ini diselesaikan sebelum fitur pelacakan waktu diaktifkan.</p>
                </div>
            @else
                @if (! empty($analysis['average_by_pillar']))
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($analysis['average_by_pillar'] as $pillar)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $pillar['label'] }}</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $pillar['average_seconds'] }}<span class="text-sm font-medium text-slate-500"> dtk/soal</span></p>
                                <p class="mt-1 text-xs text-slate-500">Batas aman: {{ $pillar['safe_seconds'] }} dtk</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (! empty($analysis['longest_questions']))
                    <div>
                        <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">5 Soal dengan Durasi Terlama</p>
                        <div class="space-y-3">
                            @foreach ($analysis['longest_questions'] as $item)
                                @php
                                    $widthPercent = min(100, (int) round(($item['seconds'] / $maxSeconds) * 100));
                                @endphp
                                <div wire:key="longest-{{ $item['sort_order'] }}" class="space-y-1.5">
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="font-bold text-rose-600">Soal {{ $item['question_number'] }}</span>
                                            <span @class([
                                                'ui-badge text-[10px]',
                                                'bg-blue-100 text-blue-700' => $item['subject_code'] === 'twk',
                                                'bg-amber-100 text-amber-700' => $item['subject_code'] === 'tiu',
                                                'bg-violet-100 text-violet-700' => $item['subject_code'] === 'tkp',
                                            ])>{{ $item['subject_label'] }}</span>
                                        </div>
                                        <span class="shrink-0 font-bold tabular-nums text-rose-600">{{ format_question_duration($item['seconds']) }} menit</span>
                                    </div>
                                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-gradient-to-r from-rose-500 to-orange-500" style="width: {{ $widthPercent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-amber-800">Ringkasan</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 leading-relaxed">
                        @foreach ($analysis['average_by_pillar'] as $pillar)
                            <li>Anda menghabiskan rata-rata <strong>{{ $pillar['average_seconds'] }} detik</strong> per soal {{ $pillar['label'] }}.</li>
                        @endforeach
                        <li>Batas aman rata-rata per soal pada ujian ini adalah <strong>{{ $safeSeconds }} detik</strong>.</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
