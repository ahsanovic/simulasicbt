<div class="min-h-screen bg-gradient-to-b from-slate-50 to-rose-50/30">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-rose-500 via-pink-600 to-rose-600 p-6 text-white shadow-xl shadow-rose-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-rose-200">Wall of Love</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Cerita Pejuang CPNS</h1>
                    <p class="mt-2 max-w-xl text-sm text-rose-100">
                        Bagikan perjalanan belajar Anda, beri energi positif pada sesama pejuang, dan dapatkan +200 XP!
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                    <svg class="h-4 w-4 text-rose-200" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    +200 XP Reward
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <div class="ui-card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total XP Belajar</p>
                    <p class="text-lg font-bold text-slate-900">{{ number_format($totalXp) }} XP</p>
                </div>
            </div>
            <button type="button" wire:click="openForm" class="ui-card flex items-center gap-3 p-4 text-left transition hover:ring-2 hover:ring-rose-200">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $userTestimonial ? 'Testimoni Anda' : 'Bagikan Cerita' }}</p>
                    <p class="text-lg font-bold text-slate-900">{{ $userTestimonial ? 'Edit Testimoni Saya' : 'Tulis Testimoni (+200 XP)' }}</p>
                </div>
            </button>
        </div>

        @if ($showForm)
            <div class="mb-8 ui-card overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $userTestimonial ? 'Edit Testimoni' : 'Tulis Testimoni Anda' }}</h2>
                            <p class="text-sm text-slate-500">Ceritakan perjalanan belajar yang menginspirasi sesama pejuang.</p>
                        </div>
                        <button type="button" wire:click="closeForm" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <form wire:submit="submit" class="space-y-6 p-6">
                    <div>
                        <label for="targetInstansi" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Formasi & Instansi Target
                        </label>
                        <input
                            id="targetInstansi"
                            type="text"
                            wire:model="targetInstansi"
                            placeholder="Contoh: Calon Auditor — Pemprov Jatim"
                            class="ui-input w-full"
                        >
                        @error('targetInstansi') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="story" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Cerita Pengalaman Belajar
                        </label>
                        <textarea
                            id="story"
                            wire:model="story"
                            rows="4"
                            placeholder="Ceritakan bagaimana platform ini membantu perjalanan belajar Anda..."
                            class="ui-input w-full resize-none"
                        ></textarea>
                        @error('story') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <x-star-rating-input :rating="$rating" />

                    <div>
                        <label for="turningPoint" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            My Turning Point <span class="font-normal text-slate-400">(opsional)</span>
                        </label>
                        <textarea
                            id="turningPoint"
                            wire:model="turningPoint"
                            rows="3"
                            placeholder="Contoh: Dulu Try Out pertama skor 280, setelah konsisten pakai Audio Mode, Try Out terakhir tembus 410!"
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
                                    wire:click="toggleTag('{{ $tag->value }}')"
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
                            <p class="text-sm font-semibold text-slate-800">Sembunyikan nama saya (Kirim sebagai Anonim)</p>
                            <p class="text-xs text-slate-500">Nama akan ditampilkan sebagai "Pejuang Pemprov ..." atau "Pejuang Kemenkumham"</p>
                        </div>
                    </label>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-60" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">{{ $userTestimonial ? 'Simpan Perubahan' : 'Kirim Testimoni' }}</span>
                            <span wire:loading wire:target="submit">Mengirim...</span>
                        </button>
                        <button type="button" wire:click="closeForm" class="ui-btn-secondary">Batal</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($testimonials->isEmpty())
            <div class="ui-card px-6 py-16 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <h2 class="mt-4 text-lg font-bold text-slate-900">Belum Ada Testimoni</h2>
                <p class="mt-2 text-sm text-slate-500">Jadilah yang pertama berbagi cerita inspiratif!</p>
                <button type="button" wire:click="openForm" class="mt-6 inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">Tulis Testimoni Pertama</button>
            </div>
        @else
            <div class="columns-1 gap-4 sm:columns-2 xl:columns-3">
                @foreach ($testimonials as $index => $testimonial)
                    <x-testimonial-card
                        :testimonial="$testimonial"
                        :featured="$index === 0 && $testimonial->reactionsScore() > 0"
                        wire:key="testimonial-{{ $testimonial->id }}"
                    />
                @endforeach
            </div>

            @if ($hasMorePages)
                <div
                    wire:intersect.margin.300px="loadMore"
                    class="flex min-h-16 items-center justify-center py-10"
                >
                    <div wire:loading wire:target="loadMore" class="flex items-center gap-2 text-sm font-medium text-slate-500">
                        <svg class="h-5 w-5 animate-spin text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Memuat testimoni lainnya...
                    </div>
                </div>
            @endif
        @endif
    </main>
</div>
