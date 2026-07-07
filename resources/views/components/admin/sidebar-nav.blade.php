@php
    $groups = [
        [
            'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Pengguna',
            'items' => [
                ['route' => 'admin.users.index', 'label' => 'Daftar Pengguna', 'icon' => 'users'],
            ],
        ],
        [
            'label' => 'Konten Ujian',
            'items' => [
                ['route' => 'admin.questions.index', 'label' => 'Bank Soal', 'icon' => 'questions'],
                ['route' => 'admin.exams.index', 'label' => 'Kelola Ujian', 'icon' => 'exams'],
            ],
        ],
        [
            'label' => 'Monitoring',
            'items' => [
                ['route' => 'admin.online-participants.index', 'label' => 'Peserta Ujian', 'icon' => 'online'],
            ],
        ],
        [
            'label' => 'Hasil & Laporan',
            'items' => [
                ['route' => 'admin.results.index', 'label' => 'Hasil Ujian', 'icon' => 'results'],
                ['route' => 'admin.testimonials.index', 'label' => 'Testimoni', 'icon' => 'testimonials'],
                ['route' => 'admin.reports.index', 'label' => 'Laporan', 'icon' => 'reports'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['route' => 'admin.settings.index', 'label' => 'Pengaturan', 'icon' => 'settings'],
            ],
        ],
    ];
@endphp

<div class="flex h-full flex-col">
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-6">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500 shadow-lg shadow-indigo-500/30">
            <x-ui.icon name="questions" class="h-5 w-5 text-white" />
        </div>
        <div>
            <p class="text-sm font-bold text-white">Simulasi CBT</p>
            <p class="text-xs text-slate-400">Panel Admin</p>
        </div>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto p-4">
        @foreach ($groups as $group)
            <div>
                @if (! empty($group['label']))
                    <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        {{ $group['label'] }}
                    </p>
                @endif

                <div class="space-y-1">
                    @foreach ($group['items'] as $item)
                        @php $active = request()->routeIs($item['route'].'*') || request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}"
                           wire:navigate
                           @click="sidebarOpen = false"
                           @class([
                               'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                               'bg-indigo-500/20 text-white ring-1 ring-indigo-400/30' => $active,
                               'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
                           ])>
                            <x-ui.icon
                                :name="$item['icon']"
                                class="h-5 w-5 shrink-0 {{ $active ? 'text-indigo-300' : 'text-slate-500 group-hover:text-slate-300' }}"
                            />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="mb-3 flex items-center gap-3 rounded-xl bg-white/5 px-3 py-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-500 text-sm font-bold text-white">
                {{ auth()->user()->initials() }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar
            </button>
        </form>
    </div>
</div>
