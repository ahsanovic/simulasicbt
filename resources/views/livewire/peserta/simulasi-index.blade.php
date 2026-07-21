<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-primary-200">Simulasi Lengkap</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Simulasi SKD Penuh</h1>
                    <p class="mt-2 max-w-2xl text-sm text-primary-100">
                        Kerjakan ujian SKD lengkap dengan timer dan skor resmi. Hasil tersimpan di riwayat tes dan menghitung XP
                        <span class="font-semibold text-amber-200">+{{ number_format($examPassXpReward) }}</span> per simulasi selesai.
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-sm font-semibold ring-1 ring-white/20">
                    <svg class="h-4 w-4 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Mode Ujian
                </div>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ujian Tersedia</h2>
                <p class="mt-0.5 text-sm text-slate-500">Pilih simulasi untuk mulai atau lanjutkan ujian yang sedang berjalan.</p>
            </div>
            <span class="ui-badge bg-primary-100 text-primary-700">{{ $exams->count() }} ujian</span>
        </div>

        <x-peserta.exam-catalog-list :exams="$exams" />
    </main>

    <x-peserta.exam-pin-modal :pin-exam-id="$pinExamId" />
    <x-exam-stress-test-modal :stress-test-exam-id="$stressTestExamId" />
</div>
