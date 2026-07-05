<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'dashboard', 'showNav' => true])]
#[Title('Dashboard Peserta')]
class Dashboard extends Component
{
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

        app(ExamService::class)->startAttempt($exam, auth()->user());

        $this->redirect(route('peserta.exam.room', $exam));
    }

    public function render()
    {
        $exams = Exam::query()
            ->where('status', 'published')
            ->withCount('questions')
            ->latest()
            ->get()
            ->reject(fn (Exam $exam) => $exam->isDuel())
            ->values();

        $attemptStats = ExamAttempt::query()
            ->where('user_id', auth()->id())
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->groupBy('exam_id');

        $hasHistory = ExamAttempt::query()
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::Submitted)
            ->exists();

        $exams = $exams->map(function (Exam $exam) use ($attemptStats) {
            /** @var Collection<int, ExamAttempt> $attempts */
            $attempts = $attemptStats->get($exam->id, collect());
            $inProgress = $attempts->first(fn ($a) => $a->status === ExamAttemptStatus::InProgress && $a->isActive());
            $completed = $attempts->where('status', ExamAttemptStatus::Submitted);

            $exam->setAttribute('in_progress_attempt', $inProgress);
            $exam->setAttribute('attempt_count', $completed->count());
            $exam->setAttribute('best_score', $completed->max('total_score') !== null ? (int) $completed->max('total_score') : null);
            $last = $completed->sortByDesc('submitted_at')->first();
            $exam->setAttribute('last_score', $last?->total_score !== null ? (int) $last->total_score : null);

            return $exam;
        });

        return view('livewire.peserta.dashboard', compact('exams', 'hasHistory'));
    }
}
