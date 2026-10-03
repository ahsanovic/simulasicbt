{{-- Page shell for Mode Sedang Ujian (exam login + closure notice). Always the official exam name, never "Simulasi". --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-primary-50/30 to-indigo-50 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center justify-center gap-6 sm:gap-8">
                <img src="{{ asset('images/jatimlogo.png') }}" alt="Pemerintah Provinsi Jawa Timur" class="h-14 w-auto max-w-[42%] object-contain sm:h-16">
                <img src="{{ asset('images/bkdlogo.png') }}" alt="BKD Provinsi Jawa Timur" class="h-14 w-auto max-w-[42%] object-contain sm:h-16">
            </div>

            <div class="mb-6 text-center">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Ujian CBT</h1>
                <p class="mt-1 text-base font-semibold text-primary-700">BKD Provinsi Jawa Timur</p>
            </div>

            <div class="ui-card p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-8 text-center text-xs text-slate-400">© {{ date('Y') }} BKD Provinsi Jawa Timur</p>
        </div>
    </div>
    @livewireScripts
</body>
</html>
