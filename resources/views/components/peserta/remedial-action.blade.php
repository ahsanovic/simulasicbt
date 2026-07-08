@props([
    'attempt',
    'remedialUnlock',
    'totalXp',
])

@php
    use App\Enums\ExamAttemptType;
    use App\Services\ExamService;
    use App\Services\GamificationService;

    $wrongCount = $attempt->isFull() && ! $attempt->isDuelAttempt() ? $attempt->wrongAnswerCount() : 0;
    $isUnlocked = $remedialUnlock['is_unlocked'];
    $estimatedMinutes = $wrongCount > 0
        ? app(ExamService::class)->remedialDurationMinutes(
            $attempt->exam,
            $wrongCount,
            $attempt->answers->count(),
        )
        : 0;
@endphp

@if ($attempt->attempt_type === ExamAttemptType::Full && ! $attempt->isDuelAttempt() && $wrongCount > 0)
    <div class="space-y-2">
        @if ($isUnlocked)
            <button type="button"
                    wire:click="startRemedial({{ $attempt->id }})"
                    wire:loading.attr="disabled"
                    wire:target="startRemedial({{ $attempt->id }})"
                    class="ui-btn-remedial inline-flex w-full items-center justify-center gap-2 sm:w-auto">
                <span wire:loading.remove wire:target="startRemedial({{ $attempt->id }})" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Ujian Remedial ({{ $wrongCount }} soal salah)
                </span>
                <span wire:loading wire:target="startRemedial({{ $attempt->id }})" class="inline-flex items-center gap-2">
                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    Memulai...
                </span>
            </button>
            <p class="text-xs text-slate-500">
                Estimasi waktu: <span class="font-semibold text-slate-700">~{{ $estimatedMinutes }} menit</span>
                · Drill hanya soal yang salah dari simulasi ini
            </p>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3">
                <button type="button"
                        disabled
                        class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white/60 px-4 py-2.5 text-sm font-semibold text-slate-400 opacity-70 backdrop-blur-sm sm:w-auto">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Remedial Otomatis (Butuh {{ number_format($remedialUnlock['threshold']) }} XP)
                </button>
                <p class="mt-2 text-xs text-slate-500">
                    XP Kamu: <span class="font-bold text-slate-700">{{ number_format($totalXp) }}</span>
                    · Sisa <span class="font-bold text-indigo-600">{{ number_format($remedialUnlock['xp_to_unlock']) }} XP</span> lagi untuk membuka fitur ini!
                </p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-200/80">
                    <div class="h-full rounded-full bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500 transition-all duration-500"
                         style="width: {{ $remedialUnlock['progress_percent'] }}%"></div>
                </div>
            </div>
        @endif
    </div>
@endif
