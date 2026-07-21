<div class="min-h-screen bg-slate-100"
     wire:key="exam-room-{{ $attemptId }}"
     wire:poll.30s="checkExpiry"
     @if ($stressTestEnabled)
         x-data="examStressTest({
             enabled: true,
             redZoneSeconds: 600,
             questionThreshold: 60,
             currentQuestionNumber: {{ $currentIndex + 1 }},
             remainingSeconds: {{ max(0, $this->remainingSeconds) }},
         })"
         x-on:exam-timer-tick.window="handleTimerTick($event)"
         x-on:question-changed.window="handleQuestionChanged($event)"
         :class="{ 'ring-4 ring-inset ring-rose-500/40 transition-shadow duration-150': showRedZoneFlash }"
     @endif>

    <x-ui.flash-toast />

    @include('livewire.peserta.exam-room.header')

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.exam-room.navigation')

            <div class="space-y-5 min-w-0">
                @include('livewire.peserta.exam-room.progress')
                @include('livewire.peserta.exam-room.help-items')
                @include('livewire.peserta.exam-room.question')
                @include('livewire.peserta.exam-room.actions')
            </div>
        </div>
    </main>

    @if ($this->currentAnswer?->question->subject->code->value === 'tiu')
        @include('livewire.peserta.exam-room.scratchpad')
    @endif
    @include('livewire.peserta.exam-room.last-question-modal')
</div>
