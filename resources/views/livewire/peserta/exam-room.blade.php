{{-- Single, unconditional root element so Livewire always binds wire:id here
     and every wire:click inside (option selection, navigation, submit) works.
     The name-confirmation screen and the exam are swapped INSIDE this root. --}}
<div wire:key="exam-room-{{ $attemptId }}">
@if ($needsNameConfirmation)
    @include('livewire.peserta.exam-room.confirm-name')
@else
<div class="min-h-screen bg-slate-100"
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

    @unless ($timeUp)
        {{-- Deadline sync, start offset randomised per participant (see exam-timer.js). --}}
        <div x-data="examDeadlinePoll({{ $isEventAttempt ? 10000 : 30000 }})" class="hidden"></div>
    @endunless

    @if ($isModeUjian)
        <x-peserta.mode-ujian-guard />
    @endif

    <x-ui.flash-toast />
    <x-peserta.exam-connection-banner />
    <x-peserta.exam-time-up-overlay :time-up="$timeUp" :result-url="$resultUrl" />

    @include('livewire.peserta.exam-room.header')

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8" @if ($isModeUjian) data-exam-content @endif>
        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.exam-room.navigation')

            <div class="space-y-5 min-w-0">
                @include('livewire.peserta.exam-room.progress')
                @unless ($isModeUjian)
                    @include('livewire.peserta.exam-room.help-items')
                @endunless
                @include('livewire.peserta.exam-room.question')
                @include('livewire.peserta.exam-room.actions')
            </div>
        </div>
    </main>

    @if (! $isModeUjian && $this->currentAnswer?->question->subject->code->value === 'tiu')
        @include('livewire.peserta.exam-room.scratchpad')
    @endif
    @include('livewire.peserta.exam-room.last-question-modal')
</div>
@endif
</div>
