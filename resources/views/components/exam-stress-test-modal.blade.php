@props([
    'stressTestExamId' => null,
])

@if ($stressTestExamId)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeStressTestModal"></div>
        <div
            class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="stress-test-modal-title"
        >
            <div class="bg-gradient-to-br from-rose-500 via-orange-500 to-amber-500 px-6 py-5 text-white">
                <h2 id="stress-test-modal-title" class="text-lg font-bold tracking-tight">Mulai Simulasi</h2>
                <p class="mt-1 text-sm text-white/85">Atur mode latihan sebelum masuk ruang ujian.</p>
            </div>

            <div class="space-y-5 p-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <label class="flex cursor-pointer items-start gap-4">
                        <input
                            type="checkbox"
                            wire:model.live="enableStressTest"
                            class="mt-1 h-5 w-5 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                        >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">Aktifkan Mode Stress-Test</span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-600">
                                Simulasi gangguan real-time: timer berdenyut di 30 menit terakhir, peringatan red-zone di 10 menit terakhir, dan ambience suara ruang ujian.
                            </span>
                        </span>
                    </label>
                </div>

                <ul class="space-y-2 text-xs text-slate-500">
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-rose-500" aria-hidden="true">●</span>
                        <span><strong class="text-slate-700">Visual:</strong> indikator tekanan waktu &amp; peringatan saat terlalu lama di satu soal.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-amber-500" aria-hidden="true">●</span>
                        <span><strong class="text-slate-700">Audio:</strong> ambience lab ujian lembut via browser (tanpa unduhan).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-emerald-500" aria-hidden="true">●</span>
                        <span><strong class="text-slate-700">Laporan:</strong> skor ketahanan stres tersedia setelah selesai di Kunci Jawaban.</span>
                    </li>
                </ul>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeStressTestModal" class="ui-btn-secondary">Batal</button>
                    <button type="button" wire:click="confirmStressTestStart" class="ui-btn-success">
                        <span wire:loading.remove wire:target="confirmStressTestStart">Mulai Simulasi</span>
                        <span wire:loading wire:target="confirmStressTestStart">Memproses…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
