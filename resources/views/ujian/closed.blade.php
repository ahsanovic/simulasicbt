{{-- Shown instead of the simulasi login / pages while Mode Sedang Ujian is on. --}}
<x-ujian-shell title="Sedang Berlangsung Ujian">
    <div class="space-y-5 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
            <svg class="h-7 w-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>

        <div>
            <h2 class="text-xl font-bold text-slate-900">Sedang Berlangsung Ujian</h2>
            <p class="mt-2 text-sm text-slate-600">
                Layanan ini sedang digunakan untuk pelaksanaan <span class="font-semibold">Ujian CBT BKD Provinsi Jawa Timur</span>, sehingga untuk sementara tidak dapat diakses.
            </p>
        </div>

        <div class="rounded-2xl border border-primary-100 bg-primary-50 px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Dapat digunakan kembali pada</p>
            <p class="mt-1 text-lg font-bold text-primary-800">
                @if ($reopensAt)
                    {{ $reopensAt->locale('id')->translatedFormat('l, j F Y \p\u\k\u\l H:i') }} WIB
                @else
                    Akan diumumkan kemudian
                @endif
            </p>
        </div>

        @if ($message)
            <p class="whitespace-pre-line rounded-2xl bg-slate-50 px-4 py-3 text-left text-sm text-slate-700">{{ $message }}</p>
        @endif

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ui-btn-secondary w-full justify-center">Keluar</button>
            </form>
        @else
            <div class="space-y-2 border-t border-slate-100 pt-5">
                <p class="text-sm text-slate-600">Peserta ujian silakan masuk melalui halaman berikut:</p>
                <a href="{{ route('ujian.login') }}" class="ui-btn-primary w-full justify-center py-3">Masuk Ujian</a>
            </div>
        @endauth
    </div>
</x-ujian-shell>
