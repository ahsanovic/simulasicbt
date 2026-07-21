<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Apply the saved theme before paint so there is no flash. Default: day mode. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('livescore-theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) { /* storage blocked — stay on day mode */ }
        })();
    </script>

    @include('partials.head', ['title' => $title ?? 'Livescore Publik'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased transition-colors dark:bg-slate-950 dark:text-slate-100">
    {{-- Pengaturan tampilan: tersembunyi, dibuka lewat tombol kecil agar tidak menutupi papan skor --}}
    <div id="livescore-controls" class="fixed right-3 top-3 z-50">
        <button type="button" id="livescore-settings" aria-expanded="false" aria-controls="livescore-panel"
                aria-label="Pengaturan tampilan" title="Pengaturan tampilan"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-500 opacity-40 shadow transition hover:opacity-100 focus:opacity-100 focus:outline-none dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </button>

        <div id="livescore-panel"
             class="absolute right-0 mt-2 hidden w-56 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <div>
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Gulir otomatis</p>
                <div class="flex items-center justify-between gap-2">
                    <button type="button" id="scroll-slower" aria-label="Perlambat gulir otomatis" title="Perlambat"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M5 12h14"/></svg>
                    </button>
                    <span id="scroll-speed-label" class="flex-1 select-none text-center text-sm font-semibold text-slate-700 dark:text-slate-200">Normal</span>
                    <button type="button" id="scroll-faster" aria-label="Percepat gulir otomatis" title="Percepat"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
            </div>

            <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                <button type="button" id="theme-toggle"
                        class="flex w-full items-center justify-between gap-2 rounded-xl px-2 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="dark:hidden">Mode gelap</span>
                    <span class="hidden dark:inline">Mode terang</span>
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                    </svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36-6.36l-1.06 1.06M6.7 17.3l-1.06 1.06m12.72 0l-1.06-1.06M6.7 6.7L5.64 5.64M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{ $slot }}

    @livewireScripts

    <script>
        // Panel pengaturan: tampil hanya saat tombolnya diklik.
        (function () {
            const trigger = document.getElementById('livescore-settings');
            const panel = document.getElementById('livescore-panel');
            const wrapper = document.getElementById('livescore-controls');
            if (!trigger || !panel) return;

            function setOpen(open) {
                panel.classList.toggle('hidden', !open);
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                trigger.classList.toggle('opacity-100', open);
                trigger.classList.toggle('opacity-40', !open);
            }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                setOpen(panel.classList.contains('hidden'));
            });

            // Tutup saat klik di luar atau tekan Escape.
            document.addEventListener('click', function (e) {
                if (wrapper && !wrapper.contains(e.target)) setOpen(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });
        })();

        // Day / dark mode toggle — remembered per browser.
        (function () {
            const btn = document.getElementById('theme-toggle');
            if (!btn) return;

            btn.addEventListener('click', function () {
                const isDark = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('livescore-theme', isDark ? 'dark' : 'light'); } catch (e) {}
            });
        })();

        // Auto-scroll for projector/display screens: slowly pan down, pause at the
        // bottom, glide back to the top, repeat. Speed is adjustable and remembered.
        (function () {
            // Pixels per second while panning down. Index is persisted.
            const SPEEDS = [
                { label: 'Mati',    px: 0   },
                { label: 'Lambat',  px: 18  },
                { label: 'Santai',  px: 28  },
                { label: 'Normal',  px: 40  },
                { label: 'Cepat',   px: 70  },
                { label: 'Kilat',   px: 120 },
            ];
            const DEFAULT_INDEX = 3;
            const STORAGE_KEY = 'livescore-scroll-speed';

            const label = document.getElementById('scroll-speed-label');
            const slower = document.getElementById('scroll-slower');
            const faster = document.getElementById('scroll-faster');
            const controls = document.getElementById('livescore-controls');

            let index = DEFAULT_INDEX;
            try {
                const stored = parseInt(localStorage.getItem(STORAGE_KEY), 10);
                if (!isNaN(stored) && stored >= 0 && stored < SPEEDS.length) index = stored;
            } catch (e) {}

            function render() {
                if (label) label.textContent = SPEEDS[index].label;
                [[slower, index === 0], [faster, index === SPEEDS.length - 1]].forEach(function (pair) {
                    const button = pair[0];
                    if (!button) return;
                    button.disabled = pair[1];
                    button.classList.toggle('opacity-30', pair[1]);
                    button.classList.toggle('cursor-not-allowed', pair[1]);
                });
            }

            function setIndex(next) {
                index = Math.max(0, Math.min(SPEEDS.length - 1, next));
                try { localStorage.setItem(STORAGE_KEY, String(index)); } catch (e) {}
                render();
            }

            if (slower) slower.addEventListener('click', () => setIndex(index - 1));
            if (faster) faster.addEventListener('click', () => setIndex(index + 1));
            render();

            // Pause briefly when the viewer interacts, but not when they are just
            // adjusting these controls.
            let paused = false;
            let pauseTimer = null;

            function pauseBriefly(ms) {
                paused = true;
                clearTimeout(pauseTimer);
                pauseTimer = setTimeout(() => { paused = false; }, ms || 4000);
            }

            ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart'].forEach((evt) =>
                window.addEventListener(evt, (e) => {
                    if (controls && e.target && controls.contains(e.target)) return;
                    pauseBriefly();
                }, { passive: true })
            );

            let position = window.scrollY;
            let goingDown = true;
            let previous = performance.now();

            // A timer (rather than requestAnimationFrame) keeps panning even when the
            // display is not the focused tab — handy for a screen left running.
            setInterval(function () {
                const now = performance.now();
                const delta = Math.min((now - previous) / 1000, 0.1); // ignore long tab-away gaps
                previous = now;

                const speed = SPEEDS[index].px;
                const max = document.documentElement.scrollHeight - window.innerHeight;

                if (paused || speed === 0 || max <= 4) {
                    position = window.scrollY; // stay in sync with manual scrolling
                    return;
                }

                if (goingDown) {
                    position += speed * delta;
                    if (position >= max) {
                        position = max;
                        goingDown = false;
                        pauseBriefly(2500);
                    }
                } else {
                    position -= speed * 3 * delta; // glide back up faster
                    if (position <= 0) {
                        position = 0;
                        goingDown = true;
                        pauseBriefly(2000);
                    }
                }

                window.scrollTo(0, position);
            }, 16);
        })();
    </script>
</body>
</html>
