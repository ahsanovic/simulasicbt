<?php

namespace App\Livewire\Concerns;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;

trait InteractsWithFullExamStart
{
    use InteractsWithStressTestModal;

    public ?int $pinExamId = null;

    public string $examPin = '';

    public function startExam(int $examId): void
    {
        $exam = Exam::query()->findOrFail($examId);

        if (! $exam->isAvailable()) {
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

        if ($exam->requiresPin()) {
            $this->pinExamId = $exam->id;
            $this->examPin = '';
            $this->resetErrorBag('examPin');

            return;
        }

        $this->promptStressTestOrBeginExam($exam);
    }

    public function confirmPin(): void
    {
        if ($this->pinExamId === null) {
            return;
        }

        $exam = Exam::query()->findOrFail($this->pinExamId);

        if (! $exam->isAvailable()) {
            $this->closePinModal();
            session()->flash('error', 'Ujian tidak tersedia saat ini.');

            return;
        }

        if (strtoupper(trim($this->examPin)) !== strtoupper((string) $exam->pin)) {
            $this->addError('examPin', 'PIN ujian salah.');

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

        $this->beginExamAttempt($exam, false);
    }

    public function closePinModal(): void
    {
        $this->pinExamId = null;
        $this->examPin = '';
        $this->resetErrorBag('examPin');
    }
}
