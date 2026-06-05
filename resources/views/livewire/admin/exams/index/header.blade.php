<x-ui.page-header title="Manajemen Ujian" description="Atur jadwal, durasi, dan soal ujian simulasi.">
    <button wire:click="openCreateModal" class="ui-btn-primary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Buat Ujian
    </button>
</x-ui.page-header>

<x-ui.flash-toast />

<div class="ui-card mb-5 p-4 sm:p-5">
    <x-ui.filter-toolbar>
        <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari judul ujian..." class="ui-input pl-10">
        </div>
    </x-ui.filter-toolbar>
</div>
