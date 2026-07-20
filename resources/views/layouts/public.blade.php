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
    <button type="button"
            id="theme-toggle"
            aria-label="Ganti mode tampilan"
            title="Ganti mode terang / gelap"
            class="fixed right-4 top-4 z-50 inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-lg transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
        {{-- Moon: shown in day mode (click to go dark) --}}
        <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
        </svg>
        {{-- Sun: shown in dark mode (click to go light) --}}
        <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.36-6.36l-1.06 1.06M6.7 17.3l-1.06 1.06m12.72 0l-1.06-1.06M6.7 6.7L5.64 5.64M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
        </svg>
    </button>

    {{ $slot }}

    @livewireScripts

    <script>
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
        // bottom, glide back to the top, and repeat. Pauses briefly on interaction.
        (function () {
            let paused = false;
            let pauseTimer = null;
            let goingDown = true;

            function pauseBriefly(ms = 4000) {
                paused = true;
                clearTimeout(pauseTimer);
                pauseTimer = setTimeout(() => { paused = false; }, ms);
            }

            ['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart'].forEach((evt) =>
                window.addEventListener(evt, () => pauseBriefly(), { passive: true })
            );

            setInterval(function () {
                if (paused) return;

                const max = document.documentElement.scrollHeight - window.innerHeight;
                if (max <= 4) return; // content fits — nothing to scroll

                if (goingDown) {
                    if (window.scrollY >= max - 1) {
                        goingDown = false;
                        pauseBriefly(2500);
                    } else {
                        window.scrollBy(0, 1);
                    }
                } else {
                    if (window.scrollY <= 1) {
                        goingDown = true;
                        pauseBriefly(2000);
                    } else {
                        window.scrollBy(0, -3);
                    }
                }
            }, 25);
        })();
    </script>
</body>
</html>
