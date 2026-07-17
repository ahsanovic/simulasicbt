<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? 'Livescore Publik'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    {{ $slot }}

    @livewireScripts

    <script>
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
