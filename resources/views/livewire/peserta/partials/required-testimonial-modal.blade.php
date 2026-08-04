<div
    class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/85 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="required-testimonial-title"
>
    @unless ($testimonialRequired)
        <div class="absolute inset-0" wire:click="closeTestimonialGate"></div>
    @endunless

    <div class="relative mx-auto flex min-h-full w-full max-w-3xl items-center justify-center">
        <div class="w-full overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/30">
            <div class="relative bg-gradient-to-r from-rose-500 via-pink-600 to-rose-600 px-6 py-5 text-white sm:px-8">
                @unless ($testimonialRequired)
                    <button type="button" wire:click="closeTestimonialGate"
                            class="absolute right-4 top-4 rounded-xl p-2 text-white/80 transition hover:bg-white/15 hover:text-white"
                            aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endunless
                <p class="text-xs font-bold uppercase tracking-widest text-rose-100">
                    {{ $testimonialRequired ? 'Satu Langkah Lagi' : 'Testimoni' }}
                </p>
                <h2 id="required-testimonial-title" class="mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                    {{ $testimonialRequired ? 'Bagikan testimoni dulu untuk melihat nilai' : 'Perbarui testimoni Anda' }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-rose-50">
                    Cerita Anda membantu kami memahami pengalaman peserta setelah mengerjakan simulasi.
                </p>
            </div>

            <form wire:submit="submitTestimonialGate" class="max-h-[75dvh] space-y-5 overflow-y-auto px-6 py-6 sm:px-8">
                <x-ui.flash-toast />

                <div>
                    <label for="gateTargetInstansi" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Formasi & Instansi Target
                    </label>
                    <input
                        id="gateTargetInstansi"
                        type="text"
                        wire:model="targetInstansi"
                        placeholder="Contoh: Calon Auditor - Pemprov Jatim"
                        class="ui-input w-full"
                    >
                    @error('targetInstansi') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gateStory" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Cerita Pengalaman Belajar
                    </label>
                    <textarea
                        id="gateStory"
                        wire:model="story"
                        rows="4"
                        placeholder="Ceritakan pengalaman Anda memakai aplikasi ini setelah menyelesaikan simulasi..."
                        class="ui-input w-full resize-none"
                    ></textarea>
                    @error('story') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                @php
                    $ratingLabels = [
                        1 => 'Kurang memuaskan',
                        2 => 'Cukup',
                        3 => 'Baik',
                        4 => 'Sangat baik',
                        5 => 'Luar biasa!',
                    ];
                @endphp

                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Rating Pengalaman <span class="text-rose-500">*</span></p>
                    <div class="flex flex-wrap items-center gap-2">
                        @for ($star = 1; $star <= 5; $star++)
                            <button
                                type="button"
                                wire:click="$set('rating', {{ $star }})"
                                @class([
                                    'rounded-lg p-1.5 transition',
                                    'bg-rose-50 ring-2 ring-rose-300' => (int) $rating === $star,
                                    'hover:bg-rose-50/70' => (int) $rating !== $star,
                                ])
                                aria-label="Beri rating {{ $star }} bintang"
                            >
                                <svg @class([
                                    'h-8 w-8',
                                    'text-amber-400' => $star <= (int) $rating,
                                    'text-slate-300' => $star > (int) $rating,
                                ]) fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </button>
                        @endfor
                        @if ((int) $rating > 0)
                            <span class="text-sm font-medium text-rose-700">{{ $ratingLabels[(int) $rating] ?? '' }}</span>
                        @endif
                    </div>
                    @error('rating') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gateTurningPoint" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        My Turning Point <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <textarea
                        id="gateTurningPoint"
                        wire:model="turningPoint"
                        rows="3"
                        placeholder="Contoh: Setelah rutin latihan, saya jadi tahu bagian mana yang harus diperbaiki."
                        class="ui-input w-full resize-none"
                    ></textarea>
                    @error('turningPoint') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">#FiturAndalan</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($featureTagOptions as $tag)
                            <button
                                type="button"
                                wire:click="toggleTestimonialTag('{{ $tag->value }}')"
                                @class([
                                    'rounded-full px-3 py-1.5 text-xs font-semibold transition ring-1',
                                    'bg-rose-600 text-white ring-rose-600' => in_array($tag->value, $selectedTags, true),
                                    'bg-white text-slate-600 ring-slate-200 hover:ring-rose-300' => ! in_array($tag->value, $selectedTags, true),
                                ])
                            >
                                {{ $tag->hashtag() }}
                            </button>
                        @endforeach
                    </div>
                    @error('selectedTags') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="checkbox" wire:model="isAnonymous" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Sembunyikan nama saya (kirim sebagai anonim)</p>
                        <p class="text-xs text-slate-500">Nama asli tidak ditampilkan pada Wall of Love.</p>
                    </div>
                </label>

                <div class="sticky bottom-0 -mx-6 flex flex-col gap-3 border-t border-slate-200 bg-white/95 px-6 py-4 sm:-mx-8 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                    <p class="text-xs text-slate-500">
                        {{ $testimonialRequired
                            ? 'Setelah testimoni terkirim, nilai simulasi Anda akan langsung tampil.'
                            : 'Perbarui cerita Anda kapan saja — ini opsional.' }}
                    </p>
                    <div class="flex items-center gap-2">
                        @unless ($testimonialRequired)
                            <button type="button" wire:click="closeTestimonialGate"
                                    class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100">
                                Batal
                            </button>
                        @endunless
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-60"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="submitTestimonialGate">{{ $testimonialRequired ? 'Kirim Testimoni & Lihat Nilai' : 'Simpan Testimoni' }}</span>
                            <span wire:loading wire:target="submitTestimonialGate">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
