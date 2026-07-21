@props([
    'hasHistory' => false,
])

<section aria-labelledby="platform-features-heading" class="mb-8">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 id="platform-features-heading" class="text-lg font-bold text-slate-900">Fitur Platform</h2>
            <p class="mt-0.5 text-sm text-slate-500">Dukungan belajar setelah Anda menyelesaikan simulasi</p>
        </div>
        <span class="hidden ui-badge bg-indigo-50 text-indigo-700 sm:inline-flex">
            <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            Berbasis AI
        </span>
    </div>

    <div
        x-data="{
            activeIndex: 0,
            cardCount: 9,
            autoplayMs: 3000,
            autoplayTimer: null,
            isInteractionPaused: false,
            isVisible: true,
            prefersReducedMotion: false,
            _isNormalizing: false,
            _visibilityObserver: null,
            getAllCards() {
                return [...(this.$refs.track?.querySelectorAll('[data-carousel-card]') ?? [])];
            },
            getClosestDomIndex() {
                const track = this.$refs.track;
                const cards = this.getAllCards();
                const scrollLeft = track.scrollLeft;
                let idx = 0;
                let minDist = Infinity;
                cards.forEach((card, i) => {
                    const dist = Math.abs(card.offsetLeft - scrollLeft);
                    if (dist < minDist) {
                        minDist = dist;
                        idx = i;
                    }
                });
                return idx;
            },
            scrollTrackTo(card, smooth = true) {
                const track = this.$refs.track;
                if (! track || ! card) return;

                track.scrollTo({
                    left: card.offsetLeft,
                    behavior: smooth ? 'smooth' : 'auto',
                });
            },
            scrollToDomIndex(index, smooth = true) {
                const card = this.getAllCards()[index];
                this.scrollTrackTo(card, smooth);
            },
            scrollTo(index) {
                const track = this.$refs.track;
                const card = track?.querySelector(`[data-carousel-card='${index}']:not([data-clone])`);
                if (! card) return;

                this.activeIndex = index;
                this.scrollTrackTo(card, true);
                this.resetAutoplay();
            },
            prev() {
                const idx = this.getClosestDomIndex();
                if (idx > 0) this.scrollToDomIndex(idx - 1);
                this.resetAutoplay();
            },
            next() {
                const cards = this.getAllCards();
                const idx = this.getClosestDomIndex();
                if (idx < cards.length - 1) this.scrollToDomIndex(idx + 1);
                this.resetAutoplay();
            },
            setupClones() {
                const track = this.$refs.track;
                if (! track || track.dataset.clonesReady) return;

                const originals = [...track.querySelectorAll('[data-carousel-card]:not([data-clone])')];

                originals.forEach((card) => {
                    const cloneEnd = card.cloneNode(true);
                    cloneEnd.setAttribute('data-clone', 'end');
                    cloneEnd.setAttribute('aria-hidden', 'true');
                    cloneEnd.setAttribute('tabindex', '-1');
                    track.appendChild(cloneEnd);
                });

                [...originals].reverse().forEach((card) => {
                    const cloneStart = card.cloneNode(true);
                    cloneStart.setAttribute('data-clone', 'start');
                    cloneStart.setAttribute('aria-hidden', 'true');
                    cloneStart.setAttribute('tabindex', '-1');
                    track.insertBefore(cloneStart, track.firstChild);
                });

                track.dataset.clonesReady = '1';
            },
            jumpToRealCard(index) {
                const track = this.$refs.track;
                const real = track?.querySelector(`[data-carousel-card='${index}']:not([data-clone])`);
                if (! real) return;

                this._isNormalizing = true;
                const prevBehavior = track.style.scrollBehavior;
                track.style.scrollBehavior = 'auto';
                track.scrollLeft = real.offsetLeft;
                track.style.scrollBehavior = prevBehavior;
                this.activeIndex = index;
                requestAnimationFrame(() => { this._isNormalizing = false; });
            },
            normalizePosition() {
                if (this._isNormalizing) return;

                const cards = this.getAllCards();
                const closest = cards[this.getClosestDomIndex()];
                if (! closest) return;

                const index = Number(closest.dataset.carouselCard);
                this.activeIndex = index;

                if (closest.hasAttribute('data-clone')) {
                    this.jumpToRealCard(index);
                }
            },
            canAutoplay() {
                return this.isVisible && ! this.isInteractionPaused && ! this.prefersReducedMotion;
            },
            startAutoplay() {
                if (! this.canAutoplay()) return;
                this.stopAutoplay();
                this.autoplayTimer = setInterval(() => this.next(), this.autoplayMs);
            },
            stopAutoplay() {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            },
            resetAutoplay() {
                this.stopAutoplay();
                this.startAutoplay();
            },
            pauseAutoplay() {
                this.isInteractionPaused = true;
                this.stopAutoplay();
            },
            resumeAutoplay() {
                this.isInteractionPaused = false;
                this.startAutoplay();
            },
            observeVisibility() {
                if (! this.$refs.track || this._visibilityObserver) return;

                this._visibilityObserver = new IntersectionObserver((entries) => {
                    this.isVisible = entries[0]?.isIntersecting ?? false;

                    if (this.isVisible) {
                        this.startAutoplay();
                    } else {
                        this.stopAutoplay();
                    }
                }, { threshold: 0.15 });

                this._visibilityObserver.observe(this.$refs.track);
            },
            init() {
                this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                this.$nextTick(() => {
                    this.setupClones();
                    const track = this.$refs.track;
                    const firstReal = track?.querySelector(`[data-carousel-card='0']:not([data-clone])`);
                    if (firstReal) {
                        track.style.scrollBehavior = 'auto';
                        track.scrollLeft = firstReal.offsetLeft;
                        track.style.scrollBehavior = '';
                        this.activeIndex = 0;
                    }

                    this.observeVisibility();
                    this.startAutoplay();
                });

                this.$refs.track?.addEventListener('scroll', () => {
                    if (this._isNormalizing) return;
                    clearTimeout(this._scrollTimer);
                    this._scrollTimer = setTimeout(() => {
                        this.normalizePosition();
                        this.resetAutoplay();
                    }, 100);
                }, { passive: true });
            },
            destroy() {
                this.stopAutoplay();
                this._visibilityObserver?.disconnect();
            },
        }"
        @mouseenter="pauseAutoplay()"
        @mouseleave="resumeAutoplay()"
        @focusin="pauseAutoplay()"
        @focusout="resumeAutoplay()"
        class="relative"
    >
        <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 z-10 hidden w-10 bg-gradient-to-r from-slate-50 to-transparent sm:block"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 z-10 hidden w-10 bg-gradient-to-l from-slate-50 to-transparent sm:block"></div>

            <div
                x-ref="track"
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 scrollbar-none [-ms-overflow-style:none] [scrollbar-width:none]"
            >
            {{-- 1. Evaluasi & Rapor Kesiapan CPNS --}}
            <a href="{{ route('peserta.evaluasi') }}"
               wire:navigate
               data-carousel-card="0"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-lg hover:shadow-primary-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-primary-100/80 to-indigo-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-indigo-600 text-white shadow-md shadow-primary-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-primary-700">Evaluasi & Rapor Kesiapan CPNS</h3>
                            <span class="ui-badge bg-primary-50 text-primary-700 text-[10px]">AI</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Analisis kelemahan materi dan rekomendasi belajar personal dari seluruh riwayat ujian Anda.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Buka rapor' : 'Tersedia setelah simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 2. Analisis Manajemen Waktu --}}
            <a href="{{ route('peserta.evaluasi', ['focus' => 'time-management']) }}"
               wire:navigate
               data-carousel-card="1"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-lg hover:shadow-orange-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-orange-100/80 to-amber-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-orange-700">Analisis Kecepatan Berpikir</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Pantau durasi per soal dan temukan pola manajemen waktu agar lebih efisien saat ujian.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-orange-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Lihat analisis' : 'Tersedia setelah simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 3. Kunci Jawaban & Pembahasan --}}
            <a href="{{ route('peserta.history', ['focus' => 'review']) }}"
               wire:navigate
               data-carousel-card="2"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-emerald-100/80 to-teal-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-700">Kunci Jawaban & Pembahasan</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Tinjau setiap soal beserta pembahasan lengkap dari riwayat tes yang sudah diselesaikan.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Buka pembahasan' : 'Tersedia setelah simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 4. Rapor Psikologi Ujian AI --}}
            <a href="{{ route('peserta.history', ['focus' => 'psychology']) }}"
               wire:navigate
               data-carousel-card="3"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-fuchsia-200 hover:shadow-lg hover:shadow-fuchsia-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-fuchsia-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-fuchsia-100/80 to-purple-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-fuchsia-500 to-purple-600 text-white shadow-md shadow-fuchsia-500/25">
                        <span class="text-lg" aria-hidden="true">🧠</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-fuchsia-700">Rapor Psikologi Ujian AI</h3>
                            <span class="ui-badge bg-fuchsia-50 text-fuchsia-700 text-[10px]">AI</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Analisis pola panik &amp; stres saat ujian.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-fuchsia-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Lihat rapor' : 'Tersedia setelah simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 5. Mode Stress-Test & Skor Ketahanan Stres --}}
            <a href="{{ $hasHistory ? route('peserta.history', ['focus' => 'review']) : route('peserta.dashboard') }}"
               wire:navigate
               data-carousel-card="4"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-red-200 hover:shadow-lg hover:shadow-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-red-100/80 to-orange-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-orange-500 text-white shadow-md shadow-red-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-red-700">Mode Stress-Test</h3>
                            <span class="ui-badge bg-red-50 text-red-700 text-[10px]">Simulasi</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Latih ketahanan mental dengan gangguan visual &amp; audio suasana ruang ujian, lalu dapatkan Skor Ketahanan Stres di pembahasan.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-red-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Lihat di pembahasan' : 'Aktifkan saat simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 6. Audio Mode --}}
            <a href="{{ route('peserta.audio.index') }}"
               wire:navigate
               data-carousel-card="5"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-lg hover:shadow-violet-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-violet-100/80 to-indigo-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-md shadow-violet-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-violet-700">Audio Mode</h3>
                            <span class="ui-badge bg-violet-50 text-violet-700 text-[10px]">Hands-free</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Mode review flashcard audio — dengarkan soal, berpikir, lalu dengarkan pembahasan tanpa interaksi wajib.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-violet-600 group-hover:gap-1.5 transition-all">
                            Mulai belajar
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 7. Kartu Sakti --}}
            <a href="{{ route('peserta.kartu-sakti.index') }}"
               wire:navigate
               data-carousel-card="6"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-lg hover:shadow-amber-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-amber-100/80 to-orange-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-md shadow-amber-500/25">
                        <span class="text-lg" aria-hidden="true">✨</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-amber-700">Kartu Sakti</h3>
                            <span class="ui-badge bg-amber-50 text-amber-700 text-[10px]">Spaced Repetition</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Kartu hafalan pintar — review 10 kartu per hari dengan jadwal otomatis agar materi masuk ingatan jangka panjang.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-amber-600 group-hover:gap-1.5 transition-all">
                            Mulai review
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>

            {{-- 8. Challenge a Friend --}}
            <a href="{{ route('peserta.duel.index') }}"
               wire:navigate
               data-carousel-card="7"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-rose-200 hover:shadow-lg hover:shadow-rose-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-rose-100/80 to-orange-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-orange-600 text-white shadow-md shadow-rose-500/25">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-rose-700">Challenge a Friend</h3>
                            <span class="ui-badge bg-rose-50 text-rose-700 text-[10px]">Duel 1v1</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Tantang teman atau lawan AI dalam mini-tryout 15 soal real-time. Skor + kecepatan menentukan pemenang.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 group-hover:gap-1.5 transition-all">
                            Mulai duel
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>
            {{-- 9. Simulasi Kelulusan Formasi --}}
            <a href="{{ $hasHistory ? route('peserta.simulasi-formasi') : route('peserta.dashboard') }}"
               wire:navigate
               data-carousel-card="8"
               class="group ui-card relative w-[88%] shrink-0 snap-start overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-lg hover:shadow-teal-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 sm:w-[calc(50%-0.375rem)] lg:w-[calc(33.333%-0.5rem)]">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-teal-100/80 to-cyan-100/40 opacity-0 transition group-hover:opacity-100"></div>

                <div class="relative flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-cyan-600 text-white shadow-md shadow-teal-500/25">
                        <span class="text-lg" aria-hidden="true">🎯</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-teal-700">Simulasi Kelulusan Formasi</h3>
                            <span class="ui-badge bg-teal-50 text-teal-700 text-[10px]">Matchmaking</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Pilih target jabatan dan bandingkan skor terbaik Anda dengan pelamar jabatan yang sama.
                        </p>
                        <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-teal-600 group-hover:gap-1.5 transition-all">
                            {{ $hasHistory ? 'Lihat simulasi' : 'Tersedia setelah simulasi' }}
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </p>
                    </div>
                </div>
            </a>
            </div>
        </div>

        <div class="relative z-20 mt-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-1.5">
                <template x-for="index in cardCount" :key="index">
                    <button type="button"
                            @click="scrollTo(index - 1)"
                            :aria-label="`Ke fitur ${index}`"
                            :class="activeIndex === (index - 1) ? 'w-5 bg-primary-600' : 'w-1.5 bg-slate-300 hover:bg-slate-400'"
                            class="h-1.5 rounded-full transition-all"></button>
                </template>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        @click="prev()"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-primary-600 bg-primary-600 text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-700 hover:border-primary-700"
                        aria-label="Fitur sebelumnya">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button"
                        @click="next()"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-primary-600 bg-primary-600 text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-700 hover:border-primary-700"
                        aria-label="Fitur berikutnya">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
</section>
