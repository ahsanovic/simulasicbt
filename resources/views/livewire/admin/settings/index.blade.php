<div>
    <x-ui.page-header title="Pengaturan" description="Konfigurasi umum aplikasi simulasi ujian.">
        <button wire:click="openModal" class="ui-btn-primary">Edit Pengaturan</button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    <div class="ui-card divide-y divide-slate-100">
        @foreach([
            ['label' => 'Nama Aplikasi', 'value' => $app_name, 'icon' => 'settings'],
            ['label' => 'Nama Instansi', 'value' => $institution_name, 'icon' => 'office'],
            ['label' => 'Durasi Default Ujian', 'value' => $default_exam_duration.' menit', 'icon' => 'clock'],
        ] as $setting)
            <div class="flex items-center gap-4 px-6 py-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                    <x-ui.icon :name="$setting['icon']" />
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">{{ $setting['label'] }}</p>
                    <p class="mt-0.5 text-base font-semibold text-slate-900">{{ $setting['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="saveLockdown" @class([
        'ui-card mt-6 space-y-5 p-6',
        'ring-2 ring-rose-400' => $lockdownActive,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="text-base font-bold text-slate-900">Mode Sedang Ujian</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Saat aktif, halaman login simulasi dan seluruh halaman simulasi ditutup dengan pengumuman.
                    Peserta ujian masuk lewat <a href="{{ route('ujian.login') }}" target="_blank" class="font-semibold text-primary-600 hover:underline">{{ route('ujian.login') }}</a>
                    dan aplikasi tampil sebagai <span class="font-semibold">{{ \App\Support\ExamLockdown::BRAND }}</span>.
                    Admin tetap bisa masuk. Akses dibuka kembali hanya saat mode ini dimatikan.
                </p>
            </div>
            <label class="inline-flex cursor-pointer items-center gap-3">
                <input type="checkbox" wire:model.live="lockdownActive" class="peer sr-only">
                <span class="relative h-7 w-12 rounded-full bg-slate-300 transition after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-rose-600 peer-checked:after:translate-x-5"></span>
                <span @class(['text-sm font-semibold', 'text-rose-700' => $lockdownActive, 'text-slate-600' => ! $lockdownActive])>
                    {{ $lockdownActive ? 'Aktif' : 'Nonaktif' }}
                </span>
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="lockdownReopensAt" class="ui-label">Dapat digunakan kembali pada</label>
                <input id="lockdownReopensAt" type="datetime-local" wire:model="lockdownReopensAt" class="ui-input">
                <p class="mt-1 text-xs text-slate-500">Hanya informasi untuk pengunjung. Kosongkan untuk "akan diumumkan kemudian".</p>
                @error('lockdownReopensAt') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="lockdownMessage" class="ui-label">Pesan tambahan (opsional)</label>
                <textarea id="lockdownMessage" wire:model="lockdownMessage" rows="3" class="ui-input" placeholder="mis. Ujian Seleksi PPPK Sesi 1–3"></textarea>
                @error('lockdownMessage') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-4">
            <button type="submit"
                    wire:confirm="{{ $lockdownActive ? 'Aktifkan Mode Sedang Ujian? Login dan halaman simulasi akan ditutup untuk umum.' : 'Simpan pengaturan Mode Sedang Ujian?' }}"
                    @class(['ui-btn-danger' => $lockdownActive, 'ui-btn-primary' => ! $lockdownActive])>
                Simpan Mode Sedang Ujian
            </button>
        </div>
    </form>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Edit Pengaturan</h2>
                </div>
                <form wire:submit="save" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label">Nama Aplikasi</label>
                        <input type="text" wire:model="app_name" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Nama Instansi</label>
                        <input type="text" wire:model="institution_name" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Durasi Default (menit)</label>
                        <input type="number" wire:model="default_exam_duration" min="1" class="ui-input">
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
