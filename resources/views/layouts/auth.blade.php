<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? 'Masuk - Simulasi CBT'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-primary-50/30 to-indigo-50 antialiased">
    <div class="flex min-h-screen">
        <!-- LEFT LAYOUT: Enhanced design -->
        <div class="relative hidden w-1/2 overflow-hidden lg:flex lg:flex-col lg:justify-between">
            <!-- Main layered gradients and overlays -->
            <div class="absolute inset-0 bg-gradient-to-br from-primary-700 via-indigo-800 to-primary-900"></div>
            <!-- Decorative SVG pattern overlay -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'80\' height=\'80\' viewBox=\'0 0 80 80\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.07\'%3E%3Ccircle cx=\'40\' cy=\'40\' r=\'40\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
            <!-- Subtle abstract colored shape top right -->
            <div class="absolute -top-24 -right-24 w-72 h-72 bg-gradient-to-tr from-indigo-400 via-sky-300 to-primary-500 rounded-full mix-blend-overlay blur-3xl opacity-50"></div>
            <!-- Subtle abstract colored shape bottom left -->
            <div class="absolute -bottom-28 -left-24 w-60 h-60 bg-gradient-to-tl from-yellow-200 via-indigo-200 to-primary-300 rounded-full mix-blend-overlay blur-3xl opacity-40"></div>
            
            <!-- Content -->
            <div class="relative z-10 flex flex-col h-full justify-between p-12">
                <div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-white/30 backdrop-blur border-2 border-white border-opacity-25 shadow-lg shadow-primary-900/20">
                        <svg class="h-8 w-8 text-white drop-shadow-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <defs>
                                <linearGradient id="logoGradient" x1="0" x2="24" y1="0" y2="24" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#facc15"/>
                                    <stop offset="1" stop-color="#818cf8"/>
                                </linearGradient>
                            </defs>
                            <path stroke="url(#logoGradient)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h1 class="mt-10 text-4xl font-extrabold tracking-tight text-white drop-shadow">Simulasi CBT</h1>
                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-indigo-100 drop-shadow">BKD Provinsi Jawa Timur</h2>
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-primary-100/90 drop-shadow-md">
                        Platform <span class="font-bold text-amber-200">ujian online</span> untuk persiapan SKD CPNS.<br />
                        <span class="text-indigo-100/80">Nikmati pengalaman modern, interaktif, dan nyaman!</span>
                    </p>
                </div>
                <!-- Row of small icon chips for vibe/artistic -->
                <div class="mt-8 flex flex-row gap-5">
                    <div class="flex items-center gap-2 bg-white/10 border border-primary-200/20 rounded-xl px-3 py-1.5 text-primary-100 text-sm font-medium shadow-sm">
                        <svg class="w-5 h-5 text-yellow-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Terang & Aman
                    </div>
                    <div class="flex items-center gap-2 bg-white/10 border border-primary-200/20 rounded-xl px-3 py-1.5 text-primary-100 text-sm font-medium shadow-sm">
                        <svg class="w-5 h-5 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Waktu Real-Time
                    </div>
                    <div class="flex items-center gap-2 bg-white/10 border border-primary-200/20 rounded-xl px-3 py-1.5 text-primary-100 text-sm font-medium shadow-sm">
                        <svg class="w-5 h-5 text-pink-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Simulasi Resmi
                    </div>
                </div>
            </div>
            <p class="relative z-10 p-8 text-xs text-primary-100/80 tracking-wide text-right font-semibold">
                © {{ date('Y') }} BKD Provinsi Jawa Timur<br>
                <span class="text-primary-200/60 font-normal">All rights reserved</span>
            </p>
        </div>
        <!-- END: LEFT LAYOUT -->

        <div class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-600 text-white">
                        <x-ui.icon name="questions" class="h-5 w-5 text-white" />
                    </div>
                    <h1 class="mt-4 text-2xl font-bold text-slate-900">Simulasi CBT</h1>
                    <p class="mt-1 text-sm text-slate-500">Masuk ke akun Anda</p>
                </div>
                <div class="ui-card p-8 shadow-xl shadow-slate-200/60">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
