@props(['active' => 'dashboard'])

@php
    $belajarActive = in_array($active, ['materi', 'audio'], true);
    $riwayatEventActive = in_array($active, ['history', 'events'], true);
    $evaluasiActive = in_array($active, ['evaluasi', 'simulasi-formasi'], true);
@endphp

<header
    x-data="{
        mobileOpen: false,
        riwayatOpen: @js($riwayatEventActive),
        evaluasiOpen: @js($evaluasiActive),
        belajarOpen: @js($belajarActive),
    }"
    @keydown.escape.window="mobileOpen = false"
    x-on:livewire:navigated.window="mobileOpen = false; riwayatOpen = false; evaluasiOpen = false; belajarOpen = false"
    class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl"
>
    <div class="relative mx-auto flex h-16 max-w-screen-2xl items-center justify-between gap-3 px-4 sm:gap-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('peserta.dashboard') }}" wire:navigate class="flex items-center gap-3">
                <img src="{{ asset('images/bkdlogo.png') }}" alt="BKD Jatim" class="h-13 w-auto object-contain">
                <div class="hidden sm:block">
                    <p class="text-sm font-bold text-slate-900">Simulasi CBT BKD Jatim</p>
                    <p class="text-xs text-slate-500">Portal Peserta</p>
                </div>
            </a>
        </div>

        {{-- Desktop navigation --}}
        <div class="hidden items-center rounded-xl bg-slate-100 p-1 text-sm font-semibold sm:flex">
            <nav class="flex items-center gap-1">
                <a href="{{ route('peserta.dashboard') }}"
                   wire:navigate
                   @class([
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
                       'bg-white text-primary-700 shadow-sm' => $active === 'dashboard',
                       'text-slate-600 hover:text-slate-900' => $active !== 'dashboard',
                   ])>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        Dashboard
                    </span>
                </a>

                <div
                    x-data="{ open: false }"
                    @click.away="open = false"
                    @keydown.escape.window="open = false"
                    x-on:livewire:navigated.window="open = false"
                    class="relative shrink-0"
                >
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="true"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition',
                            'bg-white text-primary-700 shadow-sm' => $riwayatEventActive,
                            'text-slate-600 hover:text-slate-900' => ! $riwayatEventActive,
                        ])
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                        </svg>
                        <span>Riwayat & Event</span>
                        <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 top-full z-[60] mt-1 min-w-[11.5rem] origin-top-left rounded-xl border border-slate-200 bg-white p-1 shadow-lg shadow-slate-200/50"
                        @click.stop
                    >
                        <a href="{{ route('peserta.history') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'history',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'history',
                           ])>
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                            </svg>
                            Riwayat Tes
                        </a>
                        <a href="{{ route('peserta.events.index') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'events',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'events',
                           ])>
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Event Offline
                        </a>
                    </div>
                </div>

                <div
                    x-data="{ open: false }"
                    @click.away="open = false"
                    @keydown.escape.window="open = false"
                    x-on:livewire:navigated.window="open = false"
                    class="relative shrink-0"
                >
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="true"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition',
                            'bg-white text-primary-700 shadow-sm' => $evaluasiActive,
                            'text-slate-600 hover:text-slate-900' => ! $evaluasiActive,
                        ])
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Evaluasi & Rapor</span>
                        <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 top-full z-[60] mt-1 min-w-[13rem] origin-top-left rounded-xl border border-slate-200 bg-white p-1 shadow-lg shadow-slate-200/50"
                        @click.stop
                    >
                        <a href="{{ route('peserta.evaluasi') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'evaluasi',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'evaluasi',
                           ])>
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Evaluasi Kesiapan
                        </a>
                        <a href="{{ route('peserta.simulasi-formasi') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'simulasi-formasi',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'simulasi-formasi',
                           ])>
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center text-sm" aria-hidden="true">🎯</span>
                            Simulasi Formasi
                        </a>
                    </div>
                </div>

                <div
                    x-data="{ open: false }"
                    @click.away="open = false"
                    @keydown.escape.window="open = false"
                    x-on:livewire:navigated.window="open = false"
                    class="relative shrink-0"
                >
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-haspopup="true"
                        @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition',
                            'bg-white text-primary-700 shadow-sm' => $belajarActive,
                            'text-slate-600 hover:text-slate-900' => ! $belajarActive,
                        ])
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span>Belajar</span>
                        <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 top-full z-[60] mt-1 min-w-[11.5rem] origin-top-left rounded-xl border border-slate-200 bg-white p-1 shadow-lg shadow-slate-200/50"
                        @click.stop
                    >
                        <a href="{{ route('peserta.materi.index') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'materi',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'materi',
                           ])>
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Materi Bacaan
                        </a>
                        <a href="{{ route('peserta.audio.index') }}"
                           wire:navigate
                           @click="open = false"
                           @class([
                               'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition',
                               'bg-primary-50 text-primary-700' => $active === 'audio',
                               'text-slate-700 hover:bg-slate-50' => $active !== 'audio',
                           ])>
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                            </svg>
                            Audio Mode
                        </a>
                    </div>
                </div>

                <a href="{{ route('peserta.kartu-sakti.index') }}"
                   wire:navigate
                   @class([
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
                       'bg-white text-primary-700 shadow-sm' => $active === 'kartu-sakti',
                       'text-slate-600 hover:text-slate-900' => $active !== 'kartu-sakti',
                   ])>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="text-sm leading-none" aria-hidden="true">✨</span>
                        Kartu Sakti
                    </span>
                </a>
                <a href="{{ route('peserta.shop.index') }}"
                   wire:navigate
                   @class([
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
                       'bg-white text-primary-700 shadow-sm' => $active === 'shop',
                       'text-slate-600 hover:text-slate-900' => $active !== 'shop',
                   ])>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.coin-icon class="h-4 w-4 shrink-0 text-amber-500" />
                        Toko Koin
                    </span>
                </a>
                <a href="{{ route('peserta.duel.index') }}"
                   wire:navigate
                   @class([
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
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
                       'shrink-0 rounded-lg px-3 py-1.5 transition',
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
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 sm:hidden"
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen"
                aria-controls="peserta-mobile-nav"
                aria-label="Buka menu navigasi"
            >
                <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <x-peserta.user-menu />
        </div>
    </div>

    {{-- Mobile navigation --}}
    <div
        x-show="mobileOpen"
        x-cloak
        class="sm:hidden"
    >
        <div
            class="fixed inset-0 top-16 z-40 bg-slate-900/20"
            @click="mobileOpen = false"
            aria-hidden="true"
        ></div>

        <nav
            id="peserta-mobile-nav"
            x-show="mobileOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="absolute left-0 right-0 top-full z-50 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-slate-200 bg-white px-4 py-3 shadow-lg shadow-slate-200/50"
        >
            <div class="space-y-1 text-sm font-semibold">
                <a href="{{ route('peserta.dashboard') }}"
                   wire:navigate
                   @click="mobileOpen = false"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                       'bg-primary-50 text-primary-700' => $active === 'dashboard',
                       'text-slate-700 hover:bg-slate-50' => $active !== 'dashboard',
                   ])>
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Dashboard
                </a>

                <div class="overflow-hidden rounded-xl">
                    <button
                        type="button"
                        @click="riwayatOpen = !riwayatOpen"
                        :aria-expanded="riwayatOpen"
                        @class([
                            'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition',
                            'bg-primary-50 text-primary-700' => $riwayatEventActive,
                            'text-slate-700 hover:bg-slate-50' => ! $riwayatEventActive,
                        ])
                    >
                        <span class="inline-flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                            </svg>
                            Riwayat & Event
                        </span>
                        <svg class="h-4 w-4 shrink-0 opacity-60 transition" :class="{ 'rotate-180': riwayatOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="riwayatOpen" x-cloak class="space-y-1 px-3 pb-2">
                        <a href="{{ route('peserta.history') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'history',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'history',
                           ])>
                            Riwayat Tes
                        </a>
                        <a href="{{ route('peserta.events.index') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'events',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'events',
                           ])>
                            Event Offline
                        </a>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl">
                    <button
                        type="button"
                        @click="evaluasiOpen = !evaluasiOpen"
                        :aria-expanded="evaluasiOpen"
                        @class([
                            'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition',
                            'bg-primary-50 text-primary-700' => $evaluasiActive,
                            'text-slate-700 hover:bg-slate-50' => ! $evaluasiActive,
                        ])
                    >
                        <span class="inline-flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Evaluasi & Rapor
                        </span>
                        <svg class="h-4 w-4 shrink-0 opacity-60 transition" :class="{ 'rotate-180': evaluasiOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="evaluasiOpen" x-cloak class="space-y-1 px-3 pb-2">
                        <a href="{{ route('peserta.evaluasi') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'evaluasi',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'evaluasi',
                           ])>
                            Evaluasi Kesiapan
                        </a>
                        <a href="{{ route('peserta.simulasi-formasi') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'simulasi-formasi',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'simulasi-formasi',
                           ])>
                            Simulasi Formasi
                        </a>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl">
                    <button
                        type="button"
                        @click="belajarOpen = !belajarOpen"
                        :aria-expanded="belajarOpen"
                        @class([
                            'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition',
                            'bg-primary-50 text-primary-700' => $belajarActive,
                            'text-slate-700 hover:bg-slate-50' => ! $belajarActive,
                        ])
                    >
                        <span class="inline-flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Belajar
                        </span>
                        <svg class="h-4 w-4 shrink-0 opacity-60 transition" :class="{ 'rotate-180': belajarOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="belajarOpen" x-cloak class="space-y-1 px-3 pb-2">
                        <a href="{{ route('peserta.materi.index') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'materi',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'materi',
                           ])>
                            Materi Bacaan
                        </a>
                        <a href="{{ route('peserta.audio.index') }}"
                           wire:navigate
                           @click="mobileOpen = false"
                           @class([
                               'flex items-center gap-3 rounded-lg py-2 pl-7 pr-3 text-sm transition',
                               'font-semibold text-primary-700' => $active === 'audio',
                               'text-slate-600 hover:bg-slate-50' => $active !== 'audio',
                           ])>
                            Audio Mode
                        </a>
                    </div>
                </div>

                <a href="{{ route('peserta.kartu-sakti.index') }}"
                   wire:navigate
                   @click="mobileOpen = false"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                       'bg-primary-50 text-primary-700' => $active === 'kartu-sakti',
                       'text-slate-700 hover:bg-slate-50' => $active !== 'kartu-sakti',
                   ])>
                    <span class="text-sm leading-none" aria-hidden="true">✨</span>
                    Kartu Sakti
                </a>
                <a href="{{ route('peserta.shop.index') }}"
                   wire:navigate
                   @click="mobileOpen = false"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                       'bg-primary-50 text-primary-700' => $active === 'shop',
                       'text-slate-700 hover:bg-slate-50' => $active !== 'shop',
                   ])>
                    <x-ui.coin-icon class="h-4 w-4 shrink-0 text-amber-500" />
                    Toko Koin
                </a>
                <a href="{{ route('peserta.duel.index') }}"
                   wire:navigate
                   @click="mobileOpen = false"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                       'bg-primary-50 text-primary-700' => $active === 'duel',
                       'text-slate-700 hover:bg-slate-50' => $active !== 'duel',
                   ])>
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Duel
                </a>
                <a href="{{ route('peserta.testimonials.index') }}"
                   wire:navigate
                   @click="mobileOpen = false"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 transition',
                       'bg-primary-50 text-primary-700' => $active === 'testimonials',
                       'text-slate-700 hover:bg-slate-50' => $active !== 'testimonials',
                   ])>
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    Testimoni
                </a>
            </div>
        </nav>
    </div>
</header>
