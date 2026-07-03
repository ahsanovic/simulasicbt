<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? 'Admin - Simulasi CBT'])
</head>
<body class="min-h-screen bg-slate-50" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
            x-cloak
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-white/10 bg-gradient-to-b from-slate-900 via-slate-900 to-indigo-950 transition-transform duration-300 lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            @include('components.admin.sidebar-nav')
        </aside>

        <div class="flex min-w-0 flex-1 flex-col lg:ml-72">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
                <button
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    class="rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="hidden lg:block">
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Admin Panel</p>
                    <p class="text-sm text-slate-500">Kelola simulasi ujian online</p>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 sm:inline">
                        {{ auth()->user()->role->label() }}
                    </span>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
