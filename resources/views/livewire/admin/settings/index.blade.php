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
