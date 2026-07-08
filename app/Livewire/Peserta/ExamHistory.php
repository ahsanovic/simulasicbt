<?php

namespace App\Livewire\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use App\Services\FlashcardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.peserta', ['activeNav' => 'history', 'showNav' => true])]
#[Title('Riwayat Tes')]
class ExamHistory extends Component
{
    use WithPagination;

    public bool $showResultModal = false;

    public ?ExamAttempt $resultAttempt = null;

    public ?string $focusHighlight = null;

    public function mount(): void
    {
        $focus = request()->query('focus');

        if ($focus === 'readiness') {
            $this->redirect(route('peserta.evaluasi'), navigate: true);

            return;
        }

        if ($focus === 'time-management') {
            $this->redirect(route('peserta.evaluasi', ['focus' => 'time-management']), navigate: true);

            return;
        }

        if (is_string($focus) && $focus === 'review') {
            $this->focusHighlight = $focus;
        }

        $resultAttemptId = session()->pull('show_result_attempt_id');

        if (! $resultAttemptId) {
            return;
        }

        $this->resultAttempt = ExamAttempt::query()
            ->with(['exam', 'answers.question', 'answers.selectedOption'])
            ->whereKey($resultAttemptId)
            ->where('user_id', auth()->id())
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->first();

        if ($this->resultAttempt) {
            $this->showResultModal = true;
        }
    }

    public function closeResultModal(): void
    {
        $this->showResultModal = false;
        $this->resultAttempt = null;
    }

    public function saveResultWrongToFlashcard(FlashcardService $flashcardService): void
    {
        if (! $this->resultAttempt) {
            return;
        }

        $result = $flashcardService->saveWrongAnswersFromAttempt(auth()->user(), $this->resultAttempt);

        if ($result['saved'] === 0) {
            session()->flash('warning', $result['total_candidates'] === 0
                ? 'Tidak ada soal salah yang bisa disimpan.'
                : 'Semua soal salah sudah ada di Kartu Sakti Anda.');

            return;
        }

        session()->flash('success', "{$result['saved']} soal salah disimpan ke Kartu Sakti.");
    }

    public function getResultAttemptWrongCountProperty(): int
    {
        if (! $this->resultAttempt) {
            return 0;
        }

        $this->resultAttempt->loadMissing([
            'answers.question',
            'answers.selectedOption',
        ]);

        return $this->resultAttempt->answers
            ->filter(fn ($answer) => $answer->question && ! $answer->reviewOutcome()->isPositive())
            ->count();
    }

    public function render()
    {
        $attempts = ExamAttempt::query()
            ->with('exam')
            ->where('user_id', auth()->id())
            ->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::Expired])
            ->latest('submitted_at')
            ->latest('created_at')
            ->paginate(5);

        $submittedAttempts = ExamAttempt::query()
            ->where('user_id', auth()->id())
            ->where('status', ExamAttemptStatus::Submitted)
            ->get(['score_twk', 'score_tiu', 'score_tkp', 'total_score']);

        $stats = [
            'total' => $submittedAttempts->count(),
            'average' => (int) round((float) ($submittedAttempts->avg('total_score') ?? 0)),
            'passed' => $submittedAttempts
                ->filter(fn (ExamAttempt $attempt) => exam_attempt_passes(
                    $attempt->score_twk,
                    $attempt->score_tiu,
                    $attempt->score_tkp,
                    $attempt->total_score,
                ))
                ->count(),
        ];

        return view('livewire.peserta.exam-history', [
            'attempts' => $attempts,
            'stats' => $stats,
            'passingGrades' => exam_passing_grades(),
            'scoreMax' => exam_score_max(),
            'focusHighlight' => $this->focusHighlight,
        ]);
    }
}
