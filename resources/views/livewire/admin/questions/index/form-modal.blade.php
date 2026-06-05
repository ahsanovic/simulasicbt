@if ($showModal)
    <div wire:key="question-form-{{ $editingId ?? 'new' }}" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-5 py-3.5 backdrop-blur">
                <h2 class="text-base font-bold text-slate-900">{{ $editingId ? 'Edit Soal' : 'Tambah Soal' }}</h2>
                <button type="button" wire:click="closeModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4 p-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="ui-label">Jenis Soal</label>
                        <select wire:model.live="subject_id" class="ui-select">
                            <option value="">Pilih jenis</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @error('subject_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Materi</label>
                        <select wire:model="material_id" class="ui-select" @disabled(! $subject_id)>
                            <option value="">{{ $subject_id ? 'Pilih materi' : 'Pilih jenis soal terlebih dahulu' }}</option>
                            @foreach ($modalMaterials as $material)
                                <option value="{{ $material->id }}">{{ $material->display_name }}</option>
                            @endforeach
                        </select>
                        @error('material_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="ui-label">Isi Soal</label>
                    <div wire:ignore x-data="quillEditor(@entangle('content'))" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div x-ref="editor" class="min-h-[160px]"></div>
                    </div>
                    @error('content') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="ui-label">Pembahasan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea wire:model="explanation" rows="2" class="ui-input"></textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="ui-label">Kesulitan</label>
                        <select wire:model="difficulty" class="ui-select">
                            <option value="easy">Mudah</option>
                            <option value="medium">Sedang</option>
                            <option value="hard">Sulit</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-slate-300 text-primary-600">
                            Soal aktif
                        </label>
                    </div>
                </div>

                @php
                    $isTkpSubject = $subject_id && optional($subjects->firstWhere('id', $subject_id))->code?->value === 'tkp';
                @endphp

                <div class="rounded-xl border border-slate-200 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-slate-800">Pilihan Jawaban</h3>

                    <div class="space-y-2.5">
                        @foreach ($options as $index => $option)
                            @php
                                $contentType = $option['content_type'] ?? 'text';
                                $hasUploadedImage = (isset($optionImages[$index]) && $optionImages[$index]) || ! empty($option['image_path']);
                                $isCorrect = $correctOptionIndex === $index;
                            @endphp

                            <div
                                wire:key="question-option-{{ $index }}-{{ $isTkpSubject ? 'tkp' : 'std' }}"
                                @class([
                                    'rounded-lg border bg-white',
                                    'border-emerald-300 bg-emerald-50/40' => ! $isTkpSubject && $isCorrect,
                                    'border-slate-200' => $isTkpSubject || ! $isCorrect,
                                ])
                            >
                                {{-- Baris kontrol: label, tipe konten, jawaban benar/bobot --}}
                                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2">
                                    <input
                                        type="text"
                                        wire:model="options.{{ $index }}.label"
                                        class="ui-input h-8 w-16 shrink-0 text-center text-xs font-bold"
                                        title="Label opsi"
                                        disabled
                                    >

                                    <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
                                        <button
                                            type="button"
                                            wire:click="setOptionType({{ $index }}, 'text')"
                                            @class([
                                                'rounded px-2.5 py-1 text-xs font-medium transition',
                                                'bg-white text-primary-700 shadow-sm' => $contentType === 'text',
                                                'text-slate-500 hover:text-slate-700' => $contentType !== 'text',
                                            ])
                                        >Teks</button>
                                        <button
                                            type="button"
                                            wire:click="setOptionType({{ $index }}, 'image')"
                                            @class([
                                                'rounded px-2.5 py-1 text-xs font-medium transition',
                                                'bg-white text-primary-700 shadow-sm' => $contentType === 'image',
                                                'text-slate-500 hover:text-slate-700' => $contentType !== 'image',
                                            ])
                                        >Gambar</button>
                                    </div>

                                    <div class="ml-auto">
                                        @if ($isTkpSubject)
                                            <div class="flex items-center gap-1.5 text-xs text-slate-600">
                                                <span>Bobot</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="5"
                                                    wire:key="tkp-score-{{ $index }}"
                                                    value="{{ $option['score_weight'] ?? '' }}"
                                                    wire:change="setOptionScoreWeight({{ $index }}, $event.target.value)"
                                                    class="ui-input h-8 w-14 text-center text-xs"
                                                >
                                            </div>
                                        @else
                                            <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-slate-600">
                                                <input type="radio" wire:model="correctOptionIndex" value="{{ $index }}" class="text-primary-600">
                                                Benar
                                            </label>
                                        @endif
                                    </div>
                                </div>

                                {{-- Area konten opsi --}}
                                <div class="px-3 py-2.5">
                                    @if ($contentType === 'text')
                                        <input
                                            type="text"
                                            wire:model="options.{{ $index }}.content"
                                            placeholder="Isi pilihan {{ $option['label'] }}"
                                            class="ui-input h-9 w-full text-sm"
                                        >
                                        @error("options.{$index}.content") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    @else
                                        @if ($hasUploadedImage)
                                            <div class="flex flex-wrap items-center gap-3">
                                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-1.5">
                                                    @if (isset($optionImages[$index]) && $optionImages[$index])
                                                        <img src="{{ $optionImages[$index]->temporaryUrl() }}" alt="Pratinjau {{ $option['label'] }}" class="max-h-28 max-w-[200px] rounded object-contain">
                                                    @else
                                                        <img src="{{ Storage::disk('public')->url($option['image_path']) }}" alt="Pilihan {{ $option['label'] }}" class="max-h-28 max-w-[200px] rounded object-contain">
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <label class="ui-btn-secondary cursor-pointer px-3 py-1.5 text-xs">
                                                        Ganti
                                                        <input type="file" wire:model="optionImages.{{ $index }}" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                                                    </label>
                                                    <button type="button" wire:click="removeOptionImage({{ $index }})" class="ui-btn-ghost px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50">
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                            <p wire:loading wire:target="optionImages.{{ $index }}" class="mt-1.5 text-xs text-primary-600">Mengunggah...</p>
                                        @else
                                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-primary-300 hover:bg-primary-50/20">
                                                <svg class="h-7 w-7 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="min-w-0">
                                                    <span class="block text-xs font-medium text-slate-700">Unggah gambar pilihan {{ $option['label'] }}</span>
                                                    <span class="text-[11px] text-slate-400">JPG, PNG, WEBP, GIF · maks. 5 MB</span>
                                                </span>
                                                <input type="file" wire:model="optionImages.{{ $index }}" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                                            </label>
                                            <p wire:loading wire:target="optionImages.{{ $index }}" class="mt-1.5 text-xs text-primary-600">Mengunggah...</p>
                                        @endif
                                        @error("optionImages.{$index}") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-primary">Simpan Soal</button>
                </div>
            </form>
        </div>
    </div>
@endif
