<div class="min-h-screen bg-slate-100">
    @unless ($timeUp)
        {{-- Deadline sync every 30s, start offset randomised per participant (see exam-timer.js). --}}
        <div x-data="examDeadlinePoll(30000)" class="hidden"></div>
    @endunless
    <x-peserta.mode-ujian-guard />
    <x-ui.flash-toast />
    <x-peserta.exam-connection-banner attempt-key="skb-{{ $attemptId }}" :answer-version="$answerVersionBase" />
    <x-peserta.exam-time-up-overlay :time-up="$timeUp" :result-url="$resultUrl" />

    @include('livewire.peserta.mode-ujian.skb-exam-room.header')

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8" data-exam-content>
        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.mode-ujian.skb-exam-room.navigation')

            <div class="space-y-5 min-w-0">
                @include('livewire.peserta.mode-ujian.skb-exam-room.progress')
                @include('livewire.peserta.mode-ujian.skb-exam-room.question')
                @include('livewire.peserta.mode-ujian.skb-exam-room.actions')
            </div>
        </div>
    </main>
</div>
