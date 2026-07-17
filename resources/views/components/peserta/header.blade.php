@props(['active' => 'dashboard'])

<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-screen-2xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('peserta.dashboard') }}" wire:navigate class="flex items-center gap-3">
                <img src="{{ asset('images/bkdlogo.png') }}" alt="BKD Jatim" class="h-13 w-auto object-contain">
                <div class="hidden sm:block">
                    <p class="text-sm font-bold text-slate-900">Simulasi CBT BKD Jatim</p>
                    <p class="text-xs text-slate-500">Portal Peserta</p>
                </div>
            </a>
        </div>

        <nav class="flex max-w-[min(100vw-12rem,42rem)] items-center gap-0.5 overflow-x-auto rounded-xl bg-slate-100 p-1 text-sm font-semibold scrollbar-none sm:max-w-none sm:gap-1">
            <a href="{{ route('peserta.dashboard') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'dashboard',
                   'text-slate-600 hover:text-slate-900' => $active !== 'dashboard',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Simulasi
                </span>
            </a>
            <a href="{{ route('peserta.history') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'history',
                   'text-slate-600 hover:text-slate-900' => $active !== 'history',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                    </svg>
                    <span class="hidden sm:inline">Riwayat Tes</span>
                    <span class="sm:hidden">Riwayat</span>
                </span>
            </a>
            <a href="{{ route('peserta.events.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'events',
                   'text-slate-600 hover:text-slate-900' => $active !== 'events',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="hidden sm:inline">Event Offline</span>
                    <span class="sm:hidden">Event</span>
                </span>
            </a>
            <a href="{{ route('peserta.evaluasi') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'evaluasi',
                   'text-slate-600 hover:text-slate-900' => $active !== 'evaluasi',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="hidden sm:inline">Evaluasi & Rapor</span>
                    <span class="sm:hidden">Evaluasi</span>
                </span>
            </a>
            <a href="{{ route('peserta.materi.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'materi',
                   'text-slate-600 hover:text-slate-900' => $active !== 'materi',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <span class="hidden sm:inline">Materi Belajar</span>
                    <span class="sm:hidden">Materi</span>
                </span>
            </a>
            <a href="{{ route('peserta.audio.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'audio',
                   'text-slate-600 hover:text-slate-900' => $active !== 'audio',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                    </svg>
                    <span class="hidden sm:inline">Audio Mode</span>
                    <span class="sm:hidden">Audio</span>
                </span>
            </a>
            <a href="{{ route('peserta.kartu-sakti.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'kartu-sakti',
                   'text-slate-600 hover:text-slate-900' => $active !== 'kartu-sakti',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-sm leading-none" aria-hidden="true">✨</span>
                    <span class="hidden sm:inline">Kartu Sakti</span>
                    <span class="sm:hidden">Kartu</span>
                </span>
            </a>
            <a href="{{ route('peserta.shop.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'shop',
                   'text-slate-600 hover:text-slate-900' => $active !== 'shop',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <x-ui.coin-icon class="h-4 w-4 shrink-0 text-amber-500" />
                    <span class="hidden sm:inline">Toko Koin</span>
                    <span class="sm:hidden">Toko</span>
                </span>
            </a>
            <a href="{{ route('peserta.duel.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'duel',
                   'text-slate-600 hover:text-slate-900' => $active !== 'duel',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Duel
                </span>
            </a>
            <a href="{{ route('peserta.testimonials.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'testimonials',
                   'text-slate-600 hover:text-slate-900' => $active !== 'testimonials',
               ])>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Testimoni
                </span>
            </a>
        </nav>

        <x-peserta.user-menu />
    </div>
</header>
