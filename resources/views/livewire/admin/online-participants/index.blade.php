<div>
    <x-ui.page-header title="Peserta Ujian Online" description="Pantau peserta yang sedang mengerjakan simulasi ujian secara real-time.">
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
            </span>
            {{ $this->activeAttempts->count() }} sedang ujian
        </span>
    </x-ui.page-header>

    @island(name: 'active-exams')
        <div wire:poll.10s.visible>
            @include('livewire.admin.online-participants.table')
        </div>
    @endisland
</div>
