<div>
    <x-ui.page-header title="Generate Soal AI" description="Buat soal TWK, TIU, atau TKP dengan bantuan AI. Review dan edit sebelum disimpan ke bank soal.">
        <a href="{{ route('admin.questions.index') }}" wire:navigate class="ui-btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Bank Soal
        </a>
    </x-ui.page-header>

    <x-ui.flash-toast />

    @unless ($isOpenAiConfigured)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
            <div class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-amber-900">OpenAI API Key belum dikonfigurasi</p>
                    <p class="mt-1 text-sm text-amber-800">Tambahkan <code class="rounded bg-amber-100 px-1.5 py-0.5 text-xs">OPENAI_API_KEY</code> di file <code class="rounded bg-amber-100 px-1.5 py-0.5 text-xs">.env</code> untuk menggunakan fitur ini.</p>
                </div>
            </div>
        </div>
    @endunless

    {{-- Form Konfigurasi --}}
    <div class="ui-card mb-6 overflow-hidden">
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 via-white to-violet-50 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Konfigurasi Generate</h2>
                    <p class="text-sm text-slate-500">Atur parameter soal yang akan dibuat AI</p>
                </div>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="ui-label">Jenis Soal</label>
                    <select wire:model.live="subject_id" class="ui-select">
                        <option value="">Pilih jenis</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->code->label() }} — {{ $subject->name }}</option>
                        @endforeach
                    </select>
                    @error('subject_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label">Materi</label>
                    <select wire:model="material_id" class="ui-select" @disabled(! $subject_id)>
                        <option value="">{{ $subject_id ? 'Pilih materi' : 'Pilih jenis terlebih dahulu' }}</option>
                        @foreach ($materials as $material)
                            <option value="{{ $material->id }}">{{ $material->display_name }}</option>
                        @endforeach
                    </select>
                    @error('material_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label">Tingkat Kesulitan</label>
                    <select wire:model="difficulty" class="ui-select">
                        <option value="easy">Mudah</option>
                        <option value="medium">Sedang</option>
                        <option value="hard">Sulit</option>
                    </select>
                    @error('difficulty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label">Jumlah Soal <span class="font-normal text-slate-400">(maks. {{ $maxQuestionsPerGenerate }})</span></label>
                    <input type="number" wire:model.live="questionCount" min="1" max="{{ $maxQuestionsPerGenerate }}" class="ui-input">
                    @error('questionCount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    @disabled(! $isOpenAiConfigured)
                    class="ui-btn-primary h-10 min-w-[240px] whitespace-nowrap"
                >
                    <svg wire:loading.remove wire:target="generate" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <svg wire:loading wire:target="generate" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span wire:loading.remove wire:target="generate">Generate Soal</span>
                    <span wire:loading wire:target="generate">AI sedang membuat soal...</span>
                </button>

                @if (count($generatedQuestions) > 0)
                    <button wire:click="clearPreview" wire:confirm="Hapus semua preview soal?" class="ui-btn-danger">
                        Hapus Preview
                    </button>
                    <button wire:click="approveAll" wire:confirm="Simpan semua soal valid ke bank soal?" class="ui-btn-success">
                        Approve Semua ({{ count($generatedQuestions) }})
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Preview Soal --}}
    @if (count($generatedQuestions) === 0)
        <div class="ui-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-700">Belum ada soal di-generate</h3>
            <p class="mt-2 max-w-md text-sm text-slate-500">Pilih jenis soal, materi, dan tingkat kesulitan lalu klik <strong>Generate Soal</strong>. Hasil akan muncul di sini untuk direview sebelum disimpan.</p>
        </div>
    @else
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                Preview Soal ({{ count($generatedQuestions) }})
            </h2>
            <p class="text-xs text-slate-400">Soal belum tersimpan — edit jika perlu, lalu approve</p>
        </div>

        <div class="space-y-5">
            @foreach ($generatedQuestions as $index => $question)
                @php
                    $isTkp = $selectedSubject?->code === \App\Enums\SubjectCode::Tkp;
                    $hasError = filled($question['validation_error'] ?? null);
                    $isRegenerating = $regeneratingIndex === $index;
                @endphp

                <div @class([
                    'ui-card overflow-hidden transition',
                    'ring-2 ring-red-200' => $hasError,
                ])>
                    {{-- Card Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Soal #{{ $index + 1 }}</p>
                                @if ($hasError)
                                    <p class="text-xs text-red-600">{{ $question['validation_error'] }}</p>
                                @else
                                    <p class="text-xs text-emerald-600">Valid — siap di-approve</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                wire:click="regenerate({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="regenerate"
                                @disabled($regeneratingIndex !== null)
                                class="ui-btn-secondary h-8 whitespace-nowrap text-xs"
                            >
                                @if ($isRegenerating)
                                    <svg class="h-3.5 w-3.5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span>Regenerating...</span>
                                @else
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Regenerate</span>
                                @endif
                            </button>
                            <button wire:click="removeQuestion({{ $index }})" wire:confirm="Hapus soal ini dari preview?" class="ui-btn-ghost text-xs text-red-600 hover:bg-red-50">
                                Hapus
                            </button>
                            <button wire:click="approve({{ $index }})" class="ui-btn-success text-xs">
                                Approve
                            </button>
                        </div>
                    </div>

                    {{-- Card Body --}}
                    <div class="space-y-5 p-5 sm:p-6">
                        <div>
                            <label class="ui-label">Isi Soal</label>
                            <textarea
                                wire:model.blur="generatedQuestions.{{ $index }}.content"
                                wire:change="refreshValidation({{ $index }})"
                                rows="3"
                                class="ui-input resize-y"
                                placeholder="Teks soal..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="ui-label">Pembahasan / Penjelasan Jawaban</label>
                            <textarea
                                wire:model.blur="generatedQuestions.{{ $index }}.explanation"
                                wire:change="refreshValidation({{ $index }})"
                                rows="3"
                                class="ui-input resize-y"
                                placeholder="Penjelasan mengapa jawaban benar..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="ui-label mb-3 block">
                                Pilihan Jawaban
                                @if ($isTkp)
                                    <span class="ml-1 font-normal text-slate-400">(TKP — isi bobot 1–5 per opsi)</span>
                                @else
                                    <span class="ml-1 font-normal text-slate-400">(pilih jawaban benar)</span>
                                @endif
                            </label>

                            <div class="space-y-3">
                                @foreach ($question['options'] as $optIndex => $option)
                                    <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-200">
                                        <span class="mt-2 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600">
                                            {{ $option['label'] }}
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <input
                                                type="text"
                                                wire:model.blur="generatedQuestions.{{ $index }}.options.{{ $optIndex }}.content"
                                                wire:change="refreshValidation({{ $index }})"
                                                class="ui-input"
                                                placeholder="Isi pilihan {{ $option['label'] }}"
                                            >
                                        </div>

                                        @if ($isTkp)
                                            <div class="shrink-0">
                                                <label class="mb-1 block text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400">Bobot</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="5"
                                                    wire:model.blur="generatedQuestions.{{ $index }}.options.{{ $optIndex }}.score_weight"
                                                    wire:change="refreshValidation({{ $index }})"
                                                    class="ui-input w-16 text-center"
                                                >
                                            </div>
                                        @else
                                            <label class="mt-1.5 flex shrink-0 cursor-pointer items-center gap-1.5">
                                                <input
                                                    type="radio"
                                                    name="correct_{{ $index }}"
                                                    wire:model.live="generatedQuestions.{{ $index }}.correct_option_index"
                                                    wire:change="refreshValidation({{ $index }})"
                                                    value="{{ $optIndex }}"
                                                    class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                >
                                                <span class="text-xs font-medium text-slate-500">Benar</span>
                                            </label>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Footer Actions --}}
        <div class="mt-6 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <p class="mr-auto text-sm text-slate-500">
                {{ count($generatedQuestions) }} soal menunggu approval
            </p>
            <button wire:click="clearPreview" wire:confirm="Hapus semua preview soal?" class="ui-btn-ghost">
                Batalkan Semua
            </button>
            <button wire:click="approveAll" wire:confirm="Simpan semua soal valid ke bank soal?" class="ui-btn-success">
                Approve Semua
            </button>
        </div>
    @endif
</div>
