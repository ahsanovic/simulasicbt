<?php

namespace App\Livewire\Concerns;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\ExamService;

trait InteractsWithStressTestModal
{
    public ?int $stressTestExamId = null;

    public bool $enableStressTest = false;

    protected function shouldOfferStressTestMode(Exam $exam): bool
    {
        return ! $exam->requiresPin() && ! $exam->isDuel();
    }

    protected function promptStressTestOrBeginExam(Exam $exam): void
    {
        if ($this->shouldOfferStressTestMode($exam)) {
            $this->stressTestExamId = $exam->id;
            $this->enableStressTest = false;

            return;
        }

        $this->beginExamAttempt($exam, false);
    }

    public function confirmStressTestStart(): void
    {
        if ($this->stressTestExamId === null) {
            return;
        }

        $exam = Exam::query()->findOrFail($this->stressTestExamId);

        if (! $exam->isAvailable()) {
            $this->closeStressTestModal();
            session()->flash('error', 'Ujian tidak tersedia saat ini.');

            return;
        }

        $existingAttempt = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::InProgress)
            ->first();

        if ($existingAttempt && $existingAttempt->isActive()) {
            $this->redirect(route('peserta.exam.room', $exam));

            return;
        }

        $this->beginExamAttempt($exam, $this->enableStressTest);
    }

    public function closeStressTestModal(): void
    {
        $this->stressTestExamId = null;
        $this->enableStressTest = false;
    }

    protected function beginExamAttempt(Exam $exam, bool $stressTestEnabled): void
    {
        app(ExamService::class)->startAttempt(
            $exam,
            auth()->user(),
            stressTestEnabled: $stressTestEnabled,
        );

        $this->closeStressTestModal();
        $this->redirect(route('peserta.exam.room', $exam));
    }
}
