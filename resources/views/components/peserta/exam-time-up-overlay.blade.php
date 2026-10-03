{{--
    Full-screen "Waktu Ujian Habis" lock for exam rooms using EnforcesExamDeadline.

    - The moment the browser timer reaches zero (`exam-time-up`) the screen is
      locked and the server is asked to close the attempt. If an admin added
      time in the meantime, the server answers with the new deadline
      (`exam-deadline-synced` > 0) and the lock lifts.
    - Once the server has submitted the attempt ($timeUp) the lock stays, tells
      the peserta their answers were collected, and moves on to the result page.
--}}
@props(['timeUp' => false, 'resultUrl' => null])

<div
    x-data="{ pending: false }"
    x-on:exam-time-up.window="if (! pending) { pending = true; $wire.checkExpiry(); }"
    x-on:exam-deadline-synced.window="if ($event.detail.remainingSeconds > 0) pending = false"
>
    @if ($timeUp)
        <div
            wire:key="exam-time-up-closed"
            x-data="{ left: 5 }"
            x-init="const timer = setInterval(() => { left = Math.max(0, left - 1); if (left === 0) { clearInterval(timer); window.location.assign(@js($resultUrl)); } }, 1000)"
            class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/90 p-4"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="exam-time-up-title"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-xl sm:p-8">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100">
                    <svg class="h-7 w-7 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 id="exam-time-up-title" class="mt-4 text-xl font-bold text-slate-900">Waktu Ujian Habis</h2>
                <p class="mt-2 text-sm text-slate-600">Jawaban Anda sudah dikumpulkan secara otomatis. Anda tidak dapat mengubah jawaban lagi.</p>
                <p class="mt-4 text-sm text-slate-500">Menuju halaman hasil dalam <span class="font-semibold text-slate-800" x-text="left">5</span> detik…</p>
                <a href="{{ $resultUrl }}" class="ui-btn-primary mt-5 w-full justify-center">Lihat Hasil Sekarang</a>
            </div>
        </div>
    @else
        <div
            wire:key="exam-time-up-pending"
            x-show="pending"
            x-cloak
            class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/90 p-4"
            role="alertdialog"
            aria-modal="true"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-xl sm:p-8">
                <svg class="mx-auto h-10 w-10 animate-spin text-rose-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                <h2 class="mt-4 text-xl font-bold text-slate-900">Waktu Ujian Habis</h2>
                <p class="mt-2 text-sm text-slate-600">Sedang mengumpulkan jawaban Anda. Mohon tunggu…</p>
            </div>
        </div>
    @endif
</div>
