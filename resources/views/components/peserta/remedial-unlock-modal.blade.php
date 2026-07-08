@props([
    'threshold' => \App\Services\GamificationService::REMEDIAL_UNLOCK_XP,
])

<div
    class="fixed inset-0 z-[60] overflow-y-auto p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="remedial-unlock-title"
    x-data
    x-on:keydown.escape.window="$wire.closeRemedialUnlockModal()"
>
    <div
        class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"
        wire:click="closeRemedialUnlockModal"
    ></div>

    <div class="relative mx-auto flex min-h-full w-full items-center justify-center">
        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-900/30">
            <div class="relative overflow-hidden bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 px-6 py-8 text-center text-white sm:px-8">
                <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/15"></div>
                <div class="pointer-events-none absolute -bottom-8 -left-4 h-24 w-24 rounded-full bg-white/10"></div>

                <div class="relative mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-3xl shadow-lg backdrop-blur-sm">
                    🎉
                </div>

                <h2 id="remedial-unlock-title" class="text-xl font-bold sm:text-2xl">Selamat! Kemampuan Baru Terbuka!</h2>
                <p class="mt-3 text-sm leading-relaxed text-white/90">
                    Kerja kerasmu luar biasa. Total XP-mu kini menembus
                    <span class="font-bold">{{ number_format($threshold) }} XP</span>.
                    Fitur <strong>Ujian Remedial Otomatis</strong> sekarang sudah aktif dan bisa kamu gunakan kapan saja!
                </p>
            </div>

            <div class="space-y-3 px-6 py-5 sm:px-8">
                <p class="text-center text-sm text-slate-600">
                    Ulangi hanya soal yang salah dari riwayat simulasi — waktu dihitung proporsional otomatis.
                </p>
                <button type="button"
                        wire:click="closeRemedialUnlockModal"
                        class="ui-btn-primary w-full py-3 text-sm font-semibold">
                    Mantap, Siap Drill!
                </button>
            </div>
        </div>
    </div>
</div>
