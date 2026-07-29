@php
    $user = auth()->user();
@endphp

<div
    wire:key="peserta-user-menu-{{ request()->route()?->getName() }}"
    x-data="{
        open: false,
        displayName: @js($user->name),
        initials() {
            return this.displayName
                .split(' ')
                .filter(Boolean)
                .slice(0, 2)
                .map((word) => word.charAt(0))
                .join('')
                .toUpperCase();
        },
    }"
    @click.away="open = false"
    @keydown.escape.window="open = false"
    x-on:livewire:navigated.window="open = false"
    @profile-name-updated.window="displayName = $event.detail.name; open = false"
    class="relative shrink-0"
>
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        aria-haspopup="true"
        class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold uppercase text-primary-700 ring-2 ring-transparent transition hover:bg-primary-200 focus:outline-none focus-visible:ring-primary-500"
        :title="displayName"
        x-text="initials()"
    >
        {{ $user->initials() }}
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
        class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg shadow-slate-200/50"
        @click.stop
    >
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="truncate text-sm font-semibold text-slate-900" x-text="displayName">{{ $user->name }}</p>
            <p class="text-xs text-slate-500">Peserta</p>
        </div>

        <div class="p-2">
            <button
                type="button"
                x-on:click="open = false; $dispatch('open-profile-modal')"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Profil
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                >
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</div>
