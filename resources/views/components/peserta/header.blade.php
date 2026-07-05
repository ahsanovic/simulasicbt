@props(['active' => 'dashboard'])

<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
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
                Simulasi
            </a>
            <a href="{{ route('peserta.history') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'history',
                   'text-slate-600 hover:text-slate-900' => $active !== 'history',
               ])>
                <span class="hidden sm:inline">Riwayat Tes</span>
                <span class="sm:hidden">Riwayat</span>
            </a>
            <a href="{{ route('peserta.evaluasi') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'evaluasi',
                   'text-slate-600 hover:text-slate-900' => $active !== 'evaluasi',
               ])>
                <span class="hidden sm:inline">Evaluasi & Rapor</span>
                <span class="sm:hidden">Evaluasi</span>
            </a>
            <a href="{{ route('peserta.duel.index') }}"
               wire:navigate
               @class([
                   'shrink-0 rounded-lg px-2.5 py-1.5 transition sm:px-3',
                   'bg-white text-primary-700 shadow-sm' => $active === 'duel',
                   'text-slate-600 hover:text-slate-900' => $active !== 'duel',
               ])>
                Duel
            </a>
        </nav>

        <div class="flex items-center gap-3">
            <div class="hidden text-right md:block">
                <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-500">Peserta</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ui-btn-danger py-2 text-xs">Keluar</button>
            </form>
        </div>
    </div>
</header>
