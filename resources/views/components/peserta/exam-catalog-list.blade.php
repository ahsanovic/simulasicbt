@props([
    'exams',
])

<div class="grid min-w-0 gap-4">
    @forelse ($exams as $exam)
        <article class="ui-card group overflow-hidden transition hover:shadow-lg hover:shadow-primary-500/10">
            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-slate-900 group-hover:text-primary-700">{{ $exam->title }}</h3>
                    @if ($exam->description)
                        <p class="mt-1.5 text-sm text-slate-500 line-clamp-2">{{ $exam->description }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="ui-badge bg-slate-100 text-slate-700">
                            <svg class="mr-1 inline h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $exam->duration_minutes }} menit
                        </span>
                        <span class="ui-badge bg-primary-50 text-primary-700">{{ $exam->questions_count }} soal</span>
                        @if ($exam->attempt_count > 0)
                            <span class="ui-badge bg-emerald-50 text-emerald-700">{{ $exam->attempt_count }}× dikerjakan</span>
                            @if ($exam->best_score !== null)
                                <span class="ui-badge bg-amber-50 text-amber-800">Terbaik: <x-exam-score :value="$exam->best_score" /></span>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                    @if ($exam->in_progress_attempt)
                        <button wire:click="startExam({{ $exam->id }})"
                                @disabled(! $exam->isAvailable())
                                class="ui-btn-success px-6">
                            Lanjutkan Simulasi →
                        </button>
                    @else
                        <button wire:click="startExam({{ $exam->id }})"
                                @disabled(! $exam->isAvailable())
                                @class([
                                    'ui-btn-success px-6',
                                    'opacity-50 cursor-not-allowed' => ! $exam->isAvailable(),
                                ])>
                            @if ($exam->attempt_count > 0)
                                Ulangi Simulasi →
                            @elseif ($exam->isAvailable())
                                Mulai Simulasi →
                            @else
                                Belum Tersedia
                            @endif
                        </button>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="ui-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <x-ui.icon name="exams" class="h-8 w-8" />
            </div>
            <p class="mt-4 font-semibold text-slate-700">Belum ada ujian</p>
            <p class="mt-1 text-sm text-slate-500">Belum ada ujian simulasi yang dipublikasikan.</p>
        </div>
    @endforelse
</div>
