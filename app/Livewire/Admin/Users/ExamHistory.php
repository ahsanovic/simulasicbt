<?php

namespace App\Livewire\Admin\Users;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Riwayat Tes Peserta')]
class ExamHistory extends Component
{
    use WithPagination;

    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load('instansi');
    }

    public function render()
    {
        $attempts = ExamAttempt::query()
            ->with(['exam', 'duelSession'])
            ->where('user_id', $this->user->id)
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->latest('submitted_at')
            ->latest('created_at')
            ->paginate(10);

        $submittedAttempts = ExamAttempt::query()
            ->where('user_id', $this->user->id)
            ->where('status', ExamAttemptStatus::Submitted)
            ->get(['score_twk', 'score_tiu', 'score_tkp', 'total_score']);

        $stats = [
            'total' => $submittedAttempts->count(),
            'passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_attempt_passes(
                    $attempt->score_twk,
                    $attempt->score_tiu,
                    $attempt->score_tkp,
                    $attempt->total_score,
                ))
                ->count(),
        ];

        return view('livewire.admin.users.exam-history', [
            'attempts' => $attempts,
            'stats' => $stats,
            'passingGrades' => exam_passing_grades(),
        ]);
    }
}
