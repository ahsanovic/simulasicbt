@props(['active' => 'dashboard'])

<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-screen-2xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('peserta.dashboard') }}" wire:navigate class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-indigo-600 shadow-lg shadow-primary-500/25">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>
                </div>
                <div class="hidden sm:block">
                    <p class="text-sm font-bold text-slate-900">Simulasi CBT</p>
                    <p class="text-xs text-slate-500">Portal Peserta</p>
                </div>
            </a>
        </div>

        <nav class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
            <a href="{{ route('peserta.dashboard') }}"
               wire:navigate
               @class([
                   'rounded-lg px-3 py-1.5 transition',
                   'bg-white text-primary-700 shadow-sm' => $active === 'dashboard',
                   'text-slate-600 hover:text-slate-900' => $active !== 'dashboard',
               ])>
                Simulasi
            </a>
            <a href="{{ route('peserta.history') }}"
               wire:navigate
               @class([
                   'rounded-lg px-3 py-1.5 transition',
                   'bg-white text-primary-700 shadow-sm' => $active === 'history',
                   'text-slate-600 hover:text-slate-900' => $active !== 'history',
               ])>
                Riwayat Tes
            </a>
        </nav>

        <div class="flex items-center gap-3">
            <div class="hidden text-right md:block">
                <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-500">Peserta</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ui-btn-secondary py-2 text-xs">Keluar</button>
            </form>
        </div>
    </div>
</header>
