<?php

namespace App\Livewire\Admin;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard Admin')]
class Dashboard extends Component
{
    #[Computed]
    public function activeAttempts()
    {
        return ExamAttempt::query()
            ->with(['user.instansi', 'exam'])
            ->where('status', ExamAttemptStatus::InProgress)
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'questions' => Question::query()->count(),
                'exams' => Exam::query()->count(),
                'attempts' => ExamAttempt::query()->whereNotNull('submitted_at')->count(),
            ],
        ]);
    }
}
