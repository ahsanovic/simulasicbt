<x-ui.page-header title="Manajemen Pengguna" description="Kelola akun admin dan peserta ujian.">
    <a href="{{ route('admin.users.import-template') }}" class="ui-btn-secondary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Template
    </a>
    <button wire:click="$set('showImportModal', true)" class="ui-btn-success">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import Excel
    </button>
    <button wire:click="openCreateModal" class="ui-btn-primary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Pengguna
    </button>
</x-ui.page-header>

<x-ui.flash-toast />

<div class="ui-card mb-5 p-4 sm:p-5">
    <x-ui.filter-toolbar>
        <div class="relative min-w-0 flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama, email, username..." class="ui-input pl-10">
        </div>
        <select wire:model.live="roleFilter" class="ui-select w-full sm:w-44 sm:shrink-0">
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="peserta">Peserta</option>
        </select>
    </x-ui.filter-toolbar>
</div>
