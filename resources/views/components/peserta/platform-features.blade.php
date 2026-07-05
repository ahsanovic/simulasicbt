@props([
    'hasHistory' => false,
])

<section aria-labelledby="platform-features-heading" class="mb-8">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 id="platform-features-heading" class="text-lg font-bold text-slate-900">Fitur Platform</h2>
            <p class="mt-0.5 text-sm text-slate-500">Dukungan belajar setelah Anda menyelesaikan simulasi</p>
        </div>
        <span class="hidden ui-badge bg-indigo-50 text-indigo-700 sm:inline-flex">
            <svg class="mr-1 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            Berbasis AI
        </span>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {{-- 1. Evaluasi & Rapor Kesiapan CPNS --}}
        <a href="{{ route('peserta.evaluasi') }}"
           wire:navigate
           class="group ui-card relative overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-lg hover:shadow-primary-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
            <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-primary-100/80 to-indigo-100/40 opacity-0 transition group-hover:opacity-100"></div>

            <div class="relative flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-indigo-600 text-white shadow-md shadow-primary-500/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-primary-700">Evaluasi & Rapor Kesiapan CPNS</h3>
                        <span class="ui-badge bg-primary-50 text-primary-700 text-[10px]">AI</span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Analisis kelemahan materi dan rekomendasi belajar personal dari seluruh riwayat ujian Anda.
                    </p>
                    <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 group-hover:gap-1.5 transition-all">
                        {{ $hasHistory ? 'Buka rapor' : 'Tersedia setelah simulasi' }}
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </p>
                </div>
            </div>
        </a>

        {{-- 2. Analisis Manajemen Waktu --}}
        <a href="{{ route('peserta.evaluasi', ['focus' => 'time-management']) }}"
           wire:navigate
           class="group ui-card relative overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-lg hover:shadow-orange-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
            <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-orange-100/80 to-amber-100/40 opacity-0 transition group-hover:opacity-100"></div>

            <div class="relative flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-orange-700">Analisis Kecepatan Berpikir</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Pantau durasi per soal dan temukan pola manajemen waktu agar lebih efisien saat ujian.
                    </p>
                    <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-orange-600 group-hover:gap-1.5 transition-all">
                        {{ $hasHistory ? 'Lihat analisis' : 'Tersedia setelah simulasi' }}
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </p>
                </div>
            </div>
        </a>

        {{-- 3. Kunci Jawaban & Pembahasan --}}
        <a href="{{ route('peserta.history', ['focus' => 'review']) }}"
           wire:navigate
           class="group ui-card relative overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
            <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-emerald-100/80 to-teal-100/40 opacity-0 transition group-hover:opacity-100"></div>

            <div class="relative flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-700">Kunci Jawaban & Pembahasan</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Tinjau setiap soal beserta pembahasan lengkap dari riwayat tes yang sudah diselesaikan.
                    </p>
                    <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 group-hover:gap-1.5 transition-all">
                        {{ $hasHistory ? 'Buka pembahasan' : 'Tersedia setelah simulasi' }}
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </p>
                </div>
            </div>
        </a>

        {{-- 4. Challenge a Friend --}}
        <a href="{{ route('peserta.duel.index') }}"
           wire:navigate
           class="group ui-card relative overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:border-rose-200 hover:shadow-lg hover:shadow-rose-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
            <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-gradient-to-br from-rose-100/80 to-orange-100/40 opacity-0 transition group-hover:opacity-100"></div>

            <div class="relative flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-orange-600 text-white shadow-md shadow-rose-500/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <h3 class="text-sm font-bold text-slate-900 group-hover:text-rose-700">Challenge a Friend</h3>
                        <span class="ui-badge bg-rose-50 text-rose-700 text-[10px]">Duel 1v1</span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">
                        Tantang teman atau lawan AI dalam mini-tryout 15 soal real-time. Skor + kecepatan menentukan pemenang.
                    </p>
                    <p class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 group-hover:gap-1.5 transition-all">
                        Mulai duel
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </p>
                </div>
            </div>
        </a>
    </div>
</section>
