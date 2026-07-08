<div class="min-h-screen bg-slate-100"
     wire:key="exam-room-{{ $attemptId }}"
     wire:poll.30s="checkExpiry">

    @include('livewire.peserta.exam-room.header')

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.exam-room.navigation')

            <div class="space-y-5 min-w-0">
                @include('livewire.peserta.exam-room.progress')
                @include('livewire.peserta.exam-room.question')
                @include('livewire.peserta.exam-room.actions')
            </div>
        </div>
    </main>
</div>

