@props(['attempt'])

@if ($attempt?->exam)
    <div {{ $attributes->class(['rounded-2xl border-2 border-rose-300 bg-gradient-to-r from-rose-50 to-orange-50 p-4 shadow-sm sm:p-5']) }}>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-rose-700">Simulasi Sedang Berlangsung</p>
                <p class="mt-1 truncate text-base font-bold text-slate-900">{{ $attempt->exam->title }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    Timer tetap berjalan saat Anda membuka halaman lain.
                    Sisa waktu: <span class="font-bold tabular-nums text-rose-700">{{ format_exam_remaining_time($attempt->remainingSeconds()) }}</span>
                </p>
            </div>
            <a href="{{ route('peserta.exam.room', $attempt->exam) }}"
               wire:navigate
               class="ui-btn-success shrink-0 px-6">
                Kembali ke Simulasi →
            </a>
        </div>
    </div>
@endif
