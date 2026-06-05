@if ($showImportModal)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showImportModal', false)"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">Import Peserta</h2>
                <p class="mt-1 text-sm text-slate-500">Unduh template Excel, isi data peserta, lalu unggah file di bawah.</p>
            </div>
            <form wire:submit="importParticipants" class="space-y-4 p-6">
                <a href="{{ route('admin.users.import-template') }}"
                   class="ui-btn-secondary w-full justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Template Excel
                </a>
                <p class="text-xs text-slate-500">
                    Kolom: <strong>name</strong>, <strong>email</strong>, <strong>username</strong>, <strong>password</strong>, <strong>nip</strong>, <strong>instansi_id</strong>, <strong>is_pegawai</strong> (1/0).
                    Import lebih dari 50 baris diproses di background.
                </p>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-8 transition hover:border-primary-300 hover:bg-primary-50/50">
                    <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span class="mt-3 text-sm font-medium text-slate-600">Pilih file Excel (.xlsx, .csv)</span>
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="mt-3 text-xs">
                </label>
                @error('importFile') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="importFile" class="text-sm text-slate-500">Mengunggah file...</div>
                <div wire:loading wire:target="importParticipants" class="text-sm text-slate-500">Memproses file...</div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showImportModal', false)" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-primary" wire:loading.attr="disabled" wire:target="importParticipants">Import</button>
                </div>
            </form>
        </div>
    </div>
@endif
