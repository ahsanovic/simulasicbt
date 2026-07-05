<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? 'Peserta - Simulasi CBT'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 antialiased">
    @if ($showNav ?? true)
        <x-peserta.header :active="$activeNav ?? 'dashboard'" />
    @endif

    <main class="flex-1">
        {{ $slot }}
    </main>

    <x-peserta.footer />

    @livewireScripts
    @stack('scripts')
</body>
</html>
