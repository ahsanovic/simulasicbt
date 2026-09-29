<div>
    <x-ui.page-header title="Soal SKB — {{ $jabatanSkb->name }}" description="Bank soal khusus jabatan ini, terpisah dari soal SKD.">
        <a href="{{ route('admin.jabatan-skb.index') }}" wire:navigate class="ui-btn-secondary">Kembali ke Daftar Jabatan</a>
        <button wire:click="$set('showImportModal', true)" class="ui-btn-secondary">Import Soal</button>
        <button wire:click="openCreateModal" class="ui-btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Soal
        </button>
    </x-ui.page-header>

    <x-ui.flash-toast />

    @include('livewire.admin.jabatan-skb.soal-index.import-progress')
    @include('livewire.admin.jabatan-skb.soal-index.table')
    @include('livewire.admin.jabatan-skb.soal-index.form-modal')
    @include('livewire.admin.jabatan-skb.soal-index.preview-modal')
    @include('livewire.admin.jabatan-skb.soal-index.import-modal')
    <x-ui.import-error-modal :show="$showImportErrorModal" :report="$importErrorReport" />
</div>

@push('scripts')
    @vite(['resources/js/quill-editor.js'])
@endpush
