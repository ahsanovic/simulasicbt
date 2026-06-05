@if ($showImportModal)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showImportModal', false)"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">Import Soal</h2>
                <p class="mt-1 text-sm text-slate-500">Unduh template Excel, isi data soal, lalu unggah file di bawah.</p>
            </div>
            <form wire:submit="importQuestions" class="space-y-4 p-6">
                <a href="{{ route('admin.questions.import-template') }}"
                   class="ui-btn-secondary w-full justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Unduh Template Excel
                </a>
                <p class="text-xs text-slate-500">
                    Sheet <strong>Template Soal</strong>: contoh TWK &amp; TKP. Sheet <strong>Referensi Materi</strong>: daftar <code>material_slug</code> valid.
                </p>
                <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="block w-full text-sm file:mr-4 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:font-semibold file:text-white">
                @error('importFile') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showImportModal', false)" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
@endif
