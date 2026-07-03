<x-ui.page-header title="Bank Soal" description="Kelola soal TWK, TIU, dan TKP beserta materinya.">
    <a href="{{ route('admin.questions.import-template') }}" class="ui-btn-secondary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Template
    </a>
    <button wire:click="$set('showImportModal', true)" class="ui-btn-success">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import Excel
    </button>
    <a href="{{ route('admin.questions.generate') }}" wire:navigate class="ui-btn-danger">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        Generate AI
    </a>
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
                <option value="{{ $material->id }}">{{ $material->display_name }}</option>
            @endforeach
        </select>
    </x-ui.filter-toolbar>
</div>
