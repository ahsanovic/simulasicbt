<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 via-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-10 left-1/4 h-24 w-24 rounded-full bg-indigo-400/20"></div>
            <div class="relative">
                <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-primary-100 ring-1 ring-white/20">
                    <svg class="h-3.5 w-3.5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    Berbasis AI
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Evaluasi & Rapor Kesiapan CPNS</h1>
                <p class="mt-2 max-w-2xl text-primary-100">
                    Analisis kelemahan materi, pola manajemen waktu, dan rekomendasi belajar personal dari seluruh riwayat simulasi Anda — dalam tampilan yang lebih luas dan nyaman.
                </p>
            </div>
        </div>

        @include('livewire.peserta.ai-readiness-report')
    </main>

    <x-peserta.feature-tour-init :focus="$focusHighlight" />
</div>
