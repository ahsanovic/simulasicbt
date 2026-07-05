<?php

namespace App\Livewire\Admin\Results;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Hasil Ujian')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $examTypeFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingExamTypeFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'examTypeFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $attempts = ExamAttempt::query()
            ->with(['exam', 'user', 'duelSession'])
            ->whereNotNull('submitted_at')
            ->tap(fn (Builder $query) => $this->applyFilters($query))
            ->latest('submitted_at')
            ->paginate(15);

        $submittedAttempts = ExamAttempt::query()
            ->where('status', ExamAttemptStatus::Submitted)
            ->tap(fn (Builder $query) => $this->applyFilters($query))
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

        return view('livewire.admin.results.index', [
            'attempts' => $attempts,
            'stats' => $stats,
            'passingGrades' => exam_passing_grades(),
        ]);
    }

    private function applyFilters(Builder $query): void
    {
        $query
            ->when($this->search, fn (Builder $q) => $q->whereHas('user', function (Builder $userQuery) {
                $userQuery->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->examTypeFilter === 'duel', fn (Builder $q) => $q->whereHas('exam', fn (Builder $examQuery) => $examQuery->where('settings->is_duel', true)))
            ->when($this->examTypeFilter === 'simulasi', fn (Builder $q) => $q->whereHas('exam', function (Builder $examQuery) {
                $examQuery->where(fn (Builder $settingsQuery) => $settingsQuery
                    ->whereNull('settings->is_duel')
                    ->orWhere('settings->is_duel', false));
            }));
    }
}
