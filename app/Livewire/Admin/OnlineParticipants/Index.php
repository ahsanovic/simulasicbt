<?php

namespace App\Livewire\Admin\OnlineParticipants;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Peserta Ujian Online')]
class Index extends Component
{
    #[Computed]
    public function activeAttempts()
    {
        return ExamAttempt::query()
            ->with([
                'user.instansi',
                'exam',
                'answers.selectedOption',
                'answers.question.subject',
            ])
            ->where('status', ExamAttemptStatus::InProgress)
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.online-participants.index', [
            'passingGrades' => exam_passing_grades(),
        ]);
    }
}
