<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20" wire:key="exam-review-{{ $attempt->id }}">
    <x-ui.flash-toast />

    @include('livewire.peserta.exam-review.header')

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.exam-review.navigation')

            <div class="min-w-0 space-y-5">
                @include('livewire.peserta.exam-review.summary')
                @include('livewire.peserta.exam-review.question')
                @include('livewire.peserta.exam-review.actions')
            </div>
        </div>
    </main>
</div>
