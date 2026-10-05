{{--
    Connection warning for exam rooms (see examConnection in exam-timer.js).

    A click on "Simpan" while the internet is down used to do nothing visible,
    so the peserta kept clicking and lost time. This tells them the answer is
    NOT saved yet, that the pick on screen is kept, and to click again once the
    connection is back. Hidden again after the next successful request.
--}}
@props(['attemptKey' => '', 'answerVersion' => 0])

{{-- data-attempt-key / data-answer-version seed the X-Exam-Seq answer version counter (exam-timer.js). --}}
<div x-data="examConnection" data-exam-connection data-attempt-key="{{ $attemptKey }}" data-answer-version="{{ $answerVersion }}" wire:ignore>
    <div x-show="problem" x-cloak
         class="fixed inset-x-0 top-0 z-[9000] flex justify-center p-3"
         role="alert" aria-live="assertive">
        <div class="flex w-full max-w-2xl items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-lg">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
            <div>
                <p class="font-semibold" x-text="title"></p>
                <p class="mt-0.5" x-show="offline">Pilihan Anda tetap di layar dan waktu tetap berjalan. Jangan tutup atau muat ulang halaman. Setelah koneksi kembali, klik tombol Simpan sekali lagi.</p>
                <p class="mt-0.5" x-show="! offline && saveFailed">Koneksi ke server gagal. Pilihan Anda tetap di layar. Klik tombol Simpan sekali lagi.</p>
                <p class="mt-0.5" x-show="! offline && ! saveFailed">Waktu tetap berjalan. Jangan tutup atau muat ulang halaman; tunggu sebentar hingga koneksi pulih.</p>
            </div>
        </div>
    </div>
</div>
