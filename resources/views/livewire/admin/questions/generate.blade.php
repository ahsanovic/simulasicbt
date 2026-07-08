<div>
    <x-ui.page-header title="Generate Soal AI" description="Buat soal TWK, TIU, atau TKP dengan bantuan AI. Review dan edit sebelum disimpan ke bank soal.">
        <a href="{{ route('admin.questions.index') }}" wire:navigate class="ui-btn-secondary">
            <span class="text-base leading-none" aria-hidden="true">←</span>
            Kembali ke Bank Soal
        </a>
    </x-ui.page-header>

    <x-ui.flash-toast />

    @unless ($isOpenAiConfigured)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
            <div class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-lg font-bold text-amber-600" aria-hidden="true">
                    !
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
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-xl text-white shadow-lg shadow-indigo-500/30" aria-hidden="true">
                    💡
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
                    <span wire:loading.remove wire:target="generate" class="text-base leading-none" aria-hidden="true">⚡</span>
                    <span wire:loading wire:target="generate" class="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
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
    @php
        $isTkpSkeleton = $selectedSubject?->code === \App\Enums\SubjectCode::Tkp;
    @endphp

    @if (count($generatedQuestions) === 0)
        <div wire:loading.remove wire:target="generate" class="ui-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-3xl text-slate-400" aria-hidden="true">
                📄
            </div>
            <h3 class="text-lg font-semibold text-slate-700">Belum ada soal di-generate</h3>
            <p class="mt-2 max-w-md text-sm text-slate-500">Pilih jenis soal, materi, dan tingkat kesulitan lalu klik <strong>Generate Soal</strong>. Hasil akan muncul di sini untuk direview sebelum disimpan.</p>
        </div>
    @endif

    <div wire:loading wire:target="generate" class="w-full space-y-5" aria-busy="true" aria-label="AI sedang membuat soal">
        <div class="mb-4 flex w-full flex-wrap items-center justify-between gap-2">
            <div class="h-4 w-full max-w-[10rem] animate-pulse rounded bg-slate-200 sm:w-auto"></div>
            <div class="h-3 w-full max-w-xs animate-pulse rounded bg-slate-100 sm:w-auto"></div>
        </div>

        @for ($skeletonIndex = 0; $skeletonIndex < $questionCount; $skeletonIndex++)
            <div class="ui-card w-full overflow-hidden">
                <div class="flex w-full flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-3 sm:px-6">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="h-8 w-8 shrink-0 animate-pulse rounded-lg bg-slate-200"></div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="h-4 w-full max-w-[5.5rem] animate-pulse rounded bg-slate-200"></div>
                            <div class="h-3 w-full max-w-[9rem] animate-pulse rounded bg-slate-100"></div>
                        </div>
                    </div>

                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <div class="h-8 w-full min-w-[6.5rem] flex-1 animate-pulse rounded-lg bg-slate-100 sm:w-24 sm:flex-none"></div>
                        <div class="h-8 w-full min-w-[4rem] flex-1 animate-pulse rounded-lg bg-slate-100 sm:w-16 sm:flex-none"></div>
                        <div class="h-8 w-full min-w-[5rem] flex-1 animate-pulse rounded-lg bg-slate-100 sm:w-20 sm:flex-none"></div>
                    </div>
                </div>

                <div class="w-full space-y-5 p-5 sm:p-6">
                    <div class="w-full">
                        <div class="mb-2 h-4 w-full max-w-[4.5rem] animate-pulse rounded bg-slate-200"></div>
                        <div class="h-[5.5rem] w-full animate-pulse rounded-xl border border-slate-100 bg-slate-100"></div>
                    </div>

                    <div class="w-full">
                        <div class="mb-2 h-4 w-full max-w-[15rem] animate-pulse rounded bg-slate-200"></div>
                        <div class="h-[5.5rem] w-full animate-pulse rounded-xl border border-slate-100 bg-slate-100"></div>
                    </div>

                    <div class="w-full">
                        <div class="mb-3 h-4 w-full max-w-xs animate-pulse rounded bg-slate-200"></div>

                        <div class="w-full space-y-3">
                            @foreach (range(0, 4) as $optionSkeleton)
                                <div class="flex w-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                    <div class="mt-2 h-7 w-7 shrink-0 animate-pulse rounded-lg bg-slate-100"></div>

                                    <div class="min-w-0 flex-1">
                                        <div class="h-10 w-full animate-pulse rounded-lg border border-slate-100 bg-slate-100"></div>
                                    </div>

                                    @if ($isTkpSkeleton)
                                        <div class="w-16 shrink-0 space-y-1">
                                            <div class="mx-auto h-2.5 w-10 animate-pulse rounded bg-slate-100"></div>
                                            <div class="h-10 w-full animate-pulse rounded-lg border border-slate-100 bg-slate-100"></div>
                                        </div>
                                    @else
                                        <div class="mt-1.5 flex shrink-0 items-center gap-1.5">
                                            <div class="h-4 w-4 animate-pulse rounded-full bg-slate-100"></div>
                                            <div class="h-3 w-10 animate-pulse rounded bg-slate-100"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endfor

        <div class="mt-6 flex w-full flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <div class="mr-auto h-4 w-full max-w-[11rem] animate-pulse rounded bg-slate-100"></div>
            <div class="h-10 w-full min-w-[8rem] flex-1 animate-pulse rounded-xl bg-slate-100 sm:w-32 sm:flex-none"></div>
            <div class="h-10 w-full min-w-[8rem] flex-1 animate-pulse rounded-xl bg-slate-100 sm:w-32 sm:flex-none"></div>
        </div>
    </div>

    @if (count($generatedQuestions) > 0)
        <div wire:loading.remove wire:target="generate">
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
                                    <span class="inline-block h-3.5 w-3.5 shrink-0 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" aria-hidden="true"></span>
                                    <span>Regenerating...</span>
                                @else
                                    <span class="text-sm leading-none" aria-hidden="true">↻</span>
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
                                                    wire:click="setCorrectOption({{ $index }}, {{ $optIndex }})"
                                                    @checked((int) ($question['correct_option_index'] ?? 0) === $optIndex)
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
        </div>
    @endif
</div>
