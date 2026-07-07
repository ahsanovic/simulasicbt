<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Models\ExamAttempt;
use App\Models\Instansi;
use App\Models\Question;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Laporan')]
class Index extends Component
{
    use WithPagination;

    public ?int $instansiFilter = null;

    public function updatedInstansiFilter(): void
    {
        $this->resetPage('participantsPage');
    }

    public function resetFilters(): void
    {
        $this->reset('instansiFilter');
        $this->resetPage('participantsPage');
    }

    public function render()
    {
        $instansis = Instansi::query()->orderBy('nama')->get();

        $pesertaQuery = User::query()
            ->where('role', UserRole::Peserta)
            ->when($this->instansiFilter, fn ($q) => $q->where('instansi_id', $this->instansiFilter));

        $submittedAttempts = ExamAttempt::query()
            ->where('status', ExamAttemptStatus::Submitted)
            ->get(['score_twk', 'score_tiu', 'score_tkp', 'total_score']);

        $passingGrades = exam_passing_grades();
        $submittedTotal = $submittedAttempts->count();

        $passingStats = [
            'total' => $submittedTotal,
            'passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_attempt_passes(
                    $attempt->score_twk,
                    $attempt->score_tiu,
                    $attempt->score_tkp,
                    $attempt->total_score,
                ))
                ->count(),
            'twk_passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_score_passes($attempt->score_twk, $passingGrades['twk']))
                ->count(),
            'tiu_passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_score_passes($attempt->score_tiu, $passingGrades['tiu']))
                ->count(),
            'tkp_passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_score_passes($attempt->score_tkp, $passingGrades['tkp']))
                ->count(),
            'total_score_passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_score_passes($attempt->total_score, $passingGrades['total']))
                ->count(),
        ];

        $report = [
            'total_users' => User::query()->count(),
            'total_peserta' => User::query()->where('role', UserRole::Peserta)->count(),
            'total_questions' => Question::query()->count(),
            'completed_attempts' => ExamAttempt::query()->whereNotNull('submitted_at')->count(),
            'average_score' => ExamAttempt::query()->whereNotNull('total_score')->avg('total_score') ?? 0,
        ];

        $registrationStats = [
            'total' => (clone $pesertaQuery)->count(),
            'pegawai' => (clone $pesertaQuery)->where('is_pegawai', true)->count(),
            'peserta_umum' => (clone $pesertaQuery)->where('is_pegawai', false)->count(),
            'aktif' => (clone $pesertaQuery)->where('is_active', true)->count(),
        ];

        $instansiStats = Instansi::query()
            ->withCount(['users as peserta_count' => fn ($q) => $q->where('role', UserRole::Peserta)])
            ->orderByDesc('peserta_count')
            ->orderBy('nama')
            ->get();

        $pesertaUmumCount = User::query()
            ->where('role', UserRole::Peserta)
            ->where('is_pegawai', false)
            ->count();

        $participants = $this->instansiFilter
            ? (clone $pesertaQuery)
                ->with('instansi')
                ->withSum('audioLearningSessions as audio_xp', 'xp_earned')
                ->withSum('xpRewards as reward_xp', 'amount')
                ->latest()
                ->paginate(10, pageName: 'participantsPage')
            : null;

        return view('livewire.admin.reports.index', compact(
            'report',
            'instansis',
            'registrationStats',
            'instansiStats',
            'pesertaUmumCount',
            'participants',
            'passingGrades',
            'passingStats',
        ));
    }
}
