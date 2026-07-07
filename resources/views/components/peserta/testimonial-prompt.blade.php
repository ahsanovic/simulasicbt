@php
    use App\Services\GamificationService;
    use App\Services\TestimonialService;

    $user = auth()->user();
    $shouldShow = $user !== null
        && ! request()->routeIs('peserta.exam.room', 'peserta.duel.room', 'peserta.testimonials.index')
        && app(TestimonialService::class)->shouldPromptUser($user);
@endphp

@if ($shouldShow)
    <div
        x-data="{
            visible: false,
            init() {
                const snoozedUntil = Number(localStorage.getItem('testimonial_prompt_snoozed_until') ?? 0);

                if (snoozedUntil > Date.now()) {
                    return;
                }

                this.visible = true;
            },
            dismiss() {
                localStorage.setItem(
                    'testimonial_prompt_snoozed_until',
                    String(Date.now() + 24 * 60 * 60 * 1000),
                );
                this.visible = false;
            },
        }"
        x-show="visible"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0"
        class="fixed bottom-4 right-4 z-40 w-[min(100vw-2rem,22rem)] pb-[env(safe-area-inset-bottom)]"
        role="complementary"
        aria-label="Pengingat testimoni"
    >
        <div class="overflow-hidden rounded-2xl border border-rose-200/80 bg-white shadow-xl shadow-rose-500/15 ring-1 ring-rose-100">
            <div class="bg-gradient-to-r from-rose-500 via-pink-600 to-rose-600 px-4 py-3 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-rose-100">Wall of Love</p>
                        <p class="mt-0.5 text-sm font-bold leading-snug">Bagikan cerita Anda!</p>
                    </div>
                    <button
                        type="button"
                        @click="dismiss()"
                        class="shrink-0 rounded-lg p-1 text-rose-100 transition hover:bg-white/15 hover:text-white"
                        aria-label="Tutup pengingat"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <p class="text-sm leading-relaxed text-slate-600">
                    Ceritakan perjalanan belajar Anda dan dapatkan
                    <span class="font-bold text-rose-600">+{{ GamificationService::TESTIMONIAL_XP_REWARD }} XP</span>.
                </p>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('peserta.testimonials.index', ['open' => 'form']) }}"
                        wire:navigate
                        class="inline-flex flex-1 items-center justify-center rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700"
                    >
                        Tulis Sekarang
                    </a>
                    <button
                        type="button"
                        @click="dismiss()"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                    >
                        Nanti
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
