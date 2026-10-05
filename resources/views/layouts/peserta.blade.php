<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="motion-safe:scroll-smooth">
<head>
    @include('partials.head', ['title' => $title ?? 'Peserta'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 antialiased">
    @if ($showNav ?? true)
        <x-peserta.header :active="$activeNav ?? 'dashboard'" />
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-peserta.footer />

    <x-peserta.testimonial-prompt />

    {{-- Opened from the header's user menu, so only rendered where the header is
         (not in the exam rooms, where it cost 4 XP/coin queries per page load). --}}
    @if ($showNav ?? true)
        @auth
            <livewire:peserta.profile-modal />
        @endauth
    @endif

    @livewireScripts
    @stack('scripts')
</body>
</html>
