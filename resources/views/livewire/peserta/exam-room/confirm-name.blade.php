<div class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10">
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Konfirmasi Sebelum Ujian</p>
        <h1 class="mt-1 text-xl font-bold text-slate-900">{{ $examTitle }}</h1>

        <dl class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-slate-50 text-sm">
            @if ($eventName)
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Event</dt>
                    <dd class="text-right font-semibold text-slate-800">{{ $eventName }}</dd>
                </div>
            @endif
            @if ($eventSessionName)
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Sesi</dt>
                    <dd class="text-right font-semibold text-slate-800">{{ $eventSessionName }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-slate-500">Jumlah Soal</dt>
                <dd class="text-right font-semibold text-slate-800">{{ $questionCount }} soal</dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-2.5">
                <dt class="text-slate-500">Durasi</dt>
                <dd class="text-right font-semibold text-slate-800">{{ $examDurationMinutes }} menit</dd>
            </div>
        </dl>

        <form wire:submit="confirmDisplayName" class="mt-6 space-y-2">
            <label for="displayNameInput" class="block text-sm font-semibold text-slate-800">Nama Peserta</label>
            <p class="text-xs text-slate-500">
                Nama di bawah ini diambil dari akun Google Anda. Periksa dan perbaiki bila perlu — nama ini akan
                tampil pada layar live dan dicetak pada sertifikat.
            </p>
            <input type="text"
                   id="displayNameInput"
                   wire:model="displayNameInput"
                   autofocus
                   class="ui-input w-full @error('displayNameInput') border-rose-400 @enderror"
                   placeholder="Nama lengkap sesuai identitas">
            @error('displayNameInput')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <button type="submit" class="ui-btn-primary mt-4 w-full" wire:loading.attr="disabled" wire:target="confirmDisplayName">
                <span wire:loading.remove wire:target="confirmDisplayName">Simpan &amp; Lanjut Ujian</span>
                <span wire:loading wire:target="confirmDisplayName">Menyimpan...</span>
            </button>
        </form>
    </div>
</div>
