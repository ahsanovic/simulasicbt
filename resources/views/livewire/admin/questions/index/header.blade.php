<x-ui.page-header title="Bank Soal" description="Kelola soal TWK, TIU, dan TKP beserta materinya.">
    <a href="{{ route('admin.questions.import-template') }}" class="ui-btn-secondary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Template
    </a>
    <button wire:click="$set('showImportModal', true)" class="ui-btn-success">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import Excel
    </button>
    <button wire:click="openCreateModal" class="ui-btn-primary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Soal
    </button>
</x-ui.page-header>

<x-ui.flash-toast />

<div class="ui-card mb-5 p-4 sm:p-5">
    <x-ui.filter-toolbar grid>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari isi soal..." class="ui-input">
        <select wire:model.live="subjectFilter" class="ui-select">
            <option value="">Semua Jenis</option>
            @foreach ($subjects as $subject)
                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="materialFilter" class="ui-select" @disabled(! $subjectFilter)>
            <option value="">{{ $subjectFilter ? 'Semua Materi' : 'Pilih jenis terlebih dahulu' }}</option>
            @foreach ($materials as $material)
                <option value="{{ $material->id }}">{{ $material->name }}</option>
            @endforeach
        </select>
    </x-ui.filter-toolbar>
</div>
