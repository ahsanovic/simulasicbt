<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20" wire:key="exam-review-{{ $attempt->id }}">
    <x-ui.flash-toast />

    @if ($showTimeManagementModal)
        <x-exam-time-management-modal :analysis="$this->timeAnalysis" />
    @endif

    @include('livewire.peserta.exam-review.header', [
        'wrongAnswerCount' => $this->wrongAnswerCount,
    ])

    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mb-6">
            <x-exam-psychology-report-card
                :attempt="$attempt"
                :analysis="$this->psychologyAnalysis"
                :pending="$this->psychologyReportPending"
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
            @include('livewire.peserta.exam-review.navigation')

            <div class="min-w-0 space-y-5">
                @include('livewire.peserta.exam-review.summary')
                @include('livewire.peserta.exam-review.question', [
                    'savedFlashcardQuestionIds' => $this->savedFlashcardQuestionIds,
                ])
                @include('livewire.peserta.exam-review.actions')
            </div>
        </div>
    </main>
</div>
