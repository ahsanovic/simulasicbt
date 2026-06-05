<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? 'Peserta - Simulasi CBT'])
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    @if ($showNav ?? true)
        <x-peserta.header :active="$activeNav ?? 'dashboard'" />
    @endif

    {{ $slot }}

    @livewireScripts
    @stack('scripts')
</body>
</html>
