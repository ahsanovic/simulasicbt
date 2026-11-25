{{-- Page shell for Mode Sedang Ujian (exam login + closure notice). Always the official exam name, never "Simulasi".
     Split layout: institutional panel (identity + reminders) beside the form. Pure CSS, no extra assets, so
     hundreds of participants opening it in the same minute cost nothing beyond the page itself. --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
    <style>
        @keyframes ujian-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .ujian-rise { animation: ujian-rise .6s cubic-bezier(.16, 1, .3, 1) both; }
        .ujian-rise-2 { animation-delay: .08s; }
        .ujian-rise-3 { animation-delay: .16s; }
        .ujian-grid {
            background-image:
                linear-gradient(to right, rgb(255 255 255 / .05) 1px, transparent 1px),
                linear-gradient(to bottom, rgb(255 255 255 / .05) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at 30% 20%, #000 20%, transparent 75%);
        }
        @media (prefers-reduced-motion: reduce) { .ujian-rise { animation: none; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">

        {{-- Identity panel --}}
        <aside class="relative isolate overflow-hidden bg-primary-900 px-6 py-8 text-white sm:px-10 lg:flex lg:flex-col lg:justify-between lg:px-14 lg:py-14">
            <div class="ujian-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -right-32 -z-10 h-96 w-96 rounded-full bg-primary-500/30 blur-3xl" aria-hidden="true"></div>

            <div class="ujian-rise flex items-center gap-3">
                <span class="flex items-center gap-3 rounded-2xl bg-white px-4 py-2.5 shadow-lg shadow-primary-950/30">
                    <img src="{{ asset('images/jatimlogo.png') }}" alt="Pemerintah Provinsi Jawa Timur" class="h-9 w-auto object-contain sm:h-11">
                    <img src="{{ asset('images/bkdlogo.png') }}" alt="BKD Provinsi Jawa Timur" class="h-9 w-auto object-contain sm:h-11">
                </span>
            </div>

            <div class="ujian-rise ujian-rise-2 mt-8 lg:mt-0">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-primary-200">Badan Kepegawaian Daerah</p>
                <h1 class="mt-3 text-[clamp(2.25rem,5vw,4rem)] font-extrabold leading-[0.95] tracking-[-0.03em]">
                    Ujian CBT<br><span class="text-primary-200">Provinsi Jawa Timur</span>
                </h1>
            </div>

            <ul class="ujian-rise ujian-rise-3 mt-8 hidden space-y-3 text-sm text-primary-100 sm:block lg:mt-0">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-bold">1</span>
                    Gunakan NIK dan password dari panitia.
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-bold">2</span>
                    Setelah masuk, tunggu pengawas membuka sesi dan memberikan PIN.
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-bold">3</span>
                    Jangan muat ulang halaman saat proses masuk berjalan.
                </li>
            </ul>
        </aside>

        {{-- Form panel --}}
        <main class="flex items-center justify-center px-5 py-10 sm:px-10 lg:px-14">
            <div class="ujian-rise ujian-rise-2 w-full max-w-md">
                {{ $slot }}

                <p class="mt-10 text-xs text-slate-400">© {{ date('Y') }} BKD Provinsi Jawa Timur</p>
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
