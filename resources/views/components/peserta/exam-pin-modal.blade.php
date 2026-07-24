@props([
    'pinExamId' => null,
    'examPin' => '',
])

@if ($pinExamId)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closePinModal"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h2 class="text-lg font-bold text-slate-900">Masukkan PIN Ujian</h2>
            <p class="mt-1 text-sm text-slate-500">Ujian ini dilindungi PIN. Masukkan PIN dari panitia untuk memulai.</p>
            <form wire:submit="confirmPin" class="mt-4 space-y-4">
                <div>
                    <input type="text"
                           wire:model="examPin"
                           autofocus
                           autocomplete="off"
                           maxlength="4"
                           placeholder="mis. 7K2Q"
                           class="ui-input text-center font-mono text-2xl font-bold uppercase tracking-[0.4em]">
                    @error('examPin') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closePinModal" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-success">
                        <span wire:loading.remove wire:target="confirmPin">Mulai Ujian</span>
                        <span wire:loading wire:target="confirmPin">Memproses…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
