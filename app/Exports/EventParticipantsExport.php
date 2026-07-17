<?php

namespace App\Exports;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EventParticipantsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Event $event,
        private readonly ?EventSession $session = null,
    ) {}

    public function title(): string
    {
        $label = $this->session
            ? $this->event->name.' - '.$this->session->name
            : $this->event->name;

        return mb_substr($label, 0, 31);
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'NIP',
            'Instansi',
            'Sesi',
            'Dikerjakan',
            'Total Soal',
            'Skor TWK',
            'Skor TIU',
            'Skor TKP',
            'Total Skor',
            'Status',
            'Mulai',
            'Selesai',
        ];
    }

    public function collection(): Collection
    {
        return ExamAttempt::query()
            ->where('event_id', $this->event->id)
            ->when($this->session, fn ($query) => $query->where('event_session_id', $this->session->id))
            ->with([
                'user:id,name,nip,instansi_id',
                'user.instansi:id,nama',
                'eventSession:id,name',
                'answers:id,exam_attempt_id,selected_option_id',
                'answers.selectedOption:id,question_id,score_weight,is_correct',
                'answers.question:id,subject_id',
                'answers.question.subject:id,code',
            ])
            ->get()
            ->sortBy([
                ['eventSession.name', 'asc'],
                ['user.name', 'asc'],
            ])
            ->values()
            ->map(function (ExamAttempt $attempt, int $index) {
                $total = $attempt->answers->count();
                $answered = $attempt->answers->whereNotNull('selected_option_id')->count();

                $inProgress = $attempt->status === ExamAttemptStatus::InProgress;

                if ($inProgress) {
                    $scores = $attempt->calculateScores();
                    $twk = $scores['twk'];
                    $tiu = $scores['tiu'];
                    $tkp = $scores['tkp'];
                    $totalScore = $scores['total'];
                } else {
                    $twk = (int) $attempt->score_twk;
                    $tiu = (int) $attempt->score_tiu;
                    $tkp = (int) $attempt->score_tkp;
                    $totalScore = (int) $attempt->total_score;
                }

                return [
                    $index + 1,
                    $attempt->user?->name ?? '',
                    $attempt->user?->nip ?? '',
                    $attempt->user?->instansi?->nama ?? '',
                    $attempt->eventSession?->name ?? '',
                    $answered,
                    $total,
                    $twk,
                    $tiu,
                    $tkp,
                    $totalScore,
                    $attempt->status->label(),
                    $attempt->started_at?->format('d/m/Y H:i') ?? '',
                    $attempt->submitted_at?->format('d/m/Y H:i') ?? '',
                ];
            });
    }
}
