<div class="min-h-screen bg-gradient-to-b from-slate-50 to-teal-50/30">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-teal-600 via-emerald-600 to-cyan-600 p-6 text-white shadow-xl shadow-teal-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-teal-200">Latihan Terarah</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Drill Soal</h1>
                    <p class="mt-2 max-w-2xl text-sm text-teal-100">
                        Pilih sub-materi, jumlah soal, dan durasi. Fokus pada soal yang sering salah untuk perbaikan cepat.
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                    <svg class="h-4 w-4 text-teal-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Mode Latihan
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,32%)] lg:items-start">
            <div class="space-y-6">
                <div class="ui-card p-6">
                    <h2 class="text-sm font-bold text-slate-900">Pilih Subjek</h2>
                    <p class="mt-1 text-xs text-slate-500">Drill berjalan per subjek TWK, TIU, atau TKP.</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            'twk' => ['label' => 'TWK', 'desc' => 'Wawasan Kebangsaan'],
                            'tiu' => ['label' => 'TIU', 'desc' => 'Intelegensia Umum'],
                            'tkp' => ['label' => 'TKP', 'desc' => 'Karakteristik Pribadi'],
                        ] as $code => $meta)
                            <label @class([
                                'relative cursor-pointer rounded-xl border-2 p-4 transition',
                                'border-teal-500 bg-teal-50 ring-2 ring-teal-500/20' => $subjectCode === $code,
                                'border-slate-200 hover:border-slate-300' => $subjectCode !== $code,
                            ])>
                                <input type="radio" wire:model.live="subjectCode" value="{{ $code }}" class="sr-only">
                                <p class="text-sm font-bold text-slate-900">{{ $meta['label'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $meta['desc'] }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="ui-card p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Pilih Sub-materi</h2>
                            <p class="mt-1 text-xs text-slate-500">Centang sub-materi yang ingin dilatih. Badge menunjukkan jumlah soal tersedia.</p>
                        </div>
                        <button type="button" wire:click="selectWeakMaterials" class="ui-btn-secondary text-xs">
                            Pilih Kelemahan Saya
                        </button>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse ($materialOptions as $material)
                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition',
                                'border-teal-300 bg-teal-50/60' => in_array($material['id'], $selectedMaterialIds, true),
                                'border-slate-200 hover:border-slate-300' => ! in_array($material['id'], $selectedMaterialIds, true),
                                'opacity-50' => $material['available'] < 1,
                            ])>
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedMaterialIds"
                                    value="{{ $material['id'] }}"
                                    @disabled($material['available'] < 1)
                                    class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $material['display_name'] }}</p>
                                        <span class="ui-badge bg-slate-100 text-slate-600">{{ $material['available'] }} soal</span>
                                        @if ($material['weak_count'] > 0)
                                            <span class="ui-badge bg-rose-50 text-rose-700">{{ $material['weak_count'] }} sering salah</span>
                                        @endif
                                        @if ($material['status_label'])
                                            <span class="ui-badge bg-amber-50 text-amber-700">{{ $material['status_label'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada sub-materi untuk subjek ini.</p>
                        @endforelse
                    </div>
                    @error('selectedMaterialIds') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-sm font-bold text-slate-900">Mode Fokus</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($focusModes as $mode)
                            <label @class([
                                'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                                'border-teal-500 bg-teal-50 ring-2 ring-teal-500/20' => $focusMode === $mode->value,
                                'border-slate-200 hover:border-slate-300' => $focusMode !== $mode->value,
                            ])>
                                <input type="radio" wire:model.live="focusMode" value="{{ $mode->value }}" class="mt-1 border-slate-300 text-teal-600 focus:ring-teal-500">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $mode->label() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $mode->description() }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-sm font-bold text-slate-900">Jumlah Soal & Durasi</h2>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($questionPresets as $preset)
                            <label @class([
                                'cursor-pointer rounded-xl border-2 p-4 transition',
                                'border-teal-500 bg-teal-50 ring-2 ring-teal-500/20' => $questionCount === $preset,
                                'border-slate-200 hover:border-slate-300' => $questionCount !== $preset,
                            ])>
                                <input type="radio" wire:model.live="questionCount" value="{{ $preset }}" class="sr-only">
                                <p class="text-sm font-bold text-slate-900">{{ $preset }} soal</p>
                                <p class="mt-1 text-xs text-slate-500">~{{ app(\App\Services\DrillQuestionGeneratorService::class)->suggestedDurationMinutes($preset) }} menit</p>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah soal</label>
                            <input type="number" wire:model.live="questionCount" min="{{ \App\Services\DrillQuestionGeneratorService::MIN_QUESTIONS }}" max="{{ \App\Services\DrillQuestionGeneratorService::MAX_QUESTIONS }}" class="ui-input mt-1 w-full">
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi (menit)</label>
                            <div class="mt-1 flex gap-2">
                                <input type="number" wire:model.live="durationMinutes" min="{{ \App\Services\DrillQuestionGeneratorService::MIN_DURATION_MINUTES }}" max="{{ \App\Services\DrillQuestionGeneratorService::MAX_DURATION_MINUTES }}" class="ui-input w-full">
                                @if ($durationCustomized)
                                    <button type="button" wire:click="resetDuration" class="ui-btn-secondary shrink-0 text-xs">Auto</button>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Saran: {{ $suggestedDuration }} menit</p>
                        </div>
                    </div>
                </div>

                @error('drill')
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                @enderror

                <button type="button" wire:click="startDrill" wire:loading.attr="disabled" class="ui-btn-primary w-full py-3 text-base">
                    <span wire:loading.remove wire:target="startDrill">Mulai Drill Soal</span>
                    <span wire:loading wire:target="startDrill">Menyiapkan soal...</span>
                </button>
            </div>

            <aside class="lg:sticky lg:top-6">
                <div class="ui-card p-5">
                    <h3 class="text-sm font-bold text-slate-900">Ringkasan Drill</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Subjek</dt>
                            <dd class="font-semibold text-slate-900">{{ strtoupper($subjectCode) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Sub-materi</dt>
                            <dd class="font-semibold text-slate-900">{{ count($selectedMaterialIds) }} dipilih</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Soal tersedia</dt>
                            <dd class="font-semibold text-slate-900">{{ $selectedAvailable }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Sering salah</dt>
                            <dd class="font-semibold text-rose-600">{{ $selectedWeak }} soal</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Target soal</dt>
                            <dd class="font-semibold text-slate-900">{{ min($questionCount, max(0, $selectedAvailable)) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Durasi</dt>
                            <dd class="font-semibold text-slate-900">{{ $durationMinutes }} menit</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-teal-50 px-3 py-2 text-xs text-teal-800">
                        Hasil drill tersimpan di Riwayat Tes (filter Drill). Skor drill tidak masuk leaderboard skor simulasi, tetapi +{{ \App\Services\GamificationService::DRILL_XP_REWARD }} XP tetap masuk leaderboard XP.
                    </p>
                </div>
            </aside>
        </div>
    </main>
</div>
