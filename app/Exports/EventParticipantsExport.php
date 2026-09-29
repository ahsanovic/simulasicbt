<?php

namespace App\Exports;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use App\Services\ExamService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * WithStrictNullComparison matters here: PhpSpreadsheet's default row-write
 * skips any cell where `$value != null` is false — and in PHP, 0 == null
 * and '' == null are both true. Without strict comparison every 0 score and
 * every blank date cell gets silently dropped instead of written.
 */
class EventParticipantsExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, WithCustomValueBinder, WithStrictNullComparison
{
    public function __construct(
        private readonly Event $event,
        private readonly ?EventSession $session = null,
    ) {}

    /**
     * NIP/NIK is a long digit string (16 chars for NIK) — Excel auto-detects
     * numeric-looking strings and stores them as a float, which both drops
     * leading zeros and rounds past 15 significant digits (e.g. ...001
     * becomes ...000). Column C (the NIP/NIK column in both export layouts)
     * is forced to text so the value round-trips exactly.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        $label = $this->session
            ? $this->event->name.' - '.$this->session->name
            : $this->event->name;

        return mb_substr($label, 0, 31);
    }

    public function headings(): array
    {
        if ($this->event->is_mode_ujian) {
            return $this->modeUjianHeadings();
        }

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
        if ($this->event->is_mode_ujian) {
            return $this->modeUjianCollection();
        }

        return $this->legacyCollection();
    }

    /**
     * Unchanged from before Mode Ujian existed — plain offline events (join
     * by session code, single SKD attempt per peserta) keep exactly this
     * behavior, untouched.
     */
    private function legacyCollection(): Collection
    {
        // Close out anyone whose time ran out while offline, so the export
        // reports a final status and score rather than "sedang berlangsung".
        $expired = ExamAttempt::query()
            ->where('event_id', $this->event->id)
            ->when($this->session, fn ($query) => $query->where('event_session_id', $this->session->id))
            ->expiredButOpen()
            ->get();

        if ($expired->isNotEmpty()) {
            app(ExamService::class)->finalizeExpiredAttempts($expired);
        }

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

    private function modeUjianHeadings(): array
    {
        $headings = ['No', 'Nama', 'NIP/NIK', 'Jabatan', 'Sesi'];

        if ($this->event->exam_mode->includesSkd()) {
            $headings = array_merge($headings, [
                'SKD - Dikerjakan', 'SKD - Total Soal', 'SKD - Skor TWK', 'SKD - Skor TIU', 'SKD - Skor TKP',
                'SKD - Total Skor', 'SKD - Status', 'SKD - Mulai', 'SKD - Selesai',
            ]);
        }

        if ($this->event->exam_mode->includesSkb()) {
            $headings = array_merge($headings, [
                'SKB - Benar', 'SKB - Total Soal', 'SKB - Total Skor', 'SKB - Status', 'SKB - Mulai', 'SKB - Selesai',
            ]);
        }

        return $headings;
    }

    private function modeUjianCollection(): Collection
    {
        $includesSkd = $this->event->exam_mode->includesSkd();
        $includesSkb = $this->event->exam_mode->includesSkb();

        if ($includesSkd) {
            $expired = ExamAttempt::query()
                ->where('event_id', $this->event->id)
                ->when($this->session, fn ($query) => $query->where('event_session_id', $this->session->id))
                ->expiredButOpen()
                ->get();

            if ($expired->isNotEmpty()) {
                app(ExamService::class)->finalizeExpiredAttempts($expired);
            }
        }

        $participants = EventParticipant::query()
            ->where('event_id', $this->event->id)
            ->when($this->session, fn ($query) => $query->where('event_session_id', $this->session->id))
            ->with(['user:id,name,nip,nik', 'eventSession:id,name'])
            ->get()
            ->sortBy([
                ['eventSession.name', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        // Fetched once per export (not per row) and keyed by user_id, so a
        // large participant list doesn't trigger an N+1 attempt lookup.
        $skdAttemptsByUser = $includesSkd
            ? ExamAttempt::query()
                ->where('event_id', $this->event->id)
                ->with(['answers:id,exam_attempt_id,selected_option_id'])
                ->orderBy('id')
                ->get()
                ->keyBy('user_id')
            : collect();

        $skbAttemptsByUser = $includesSkb
            ? SkbExamAttempt::query()
                ->where('event_id', $this->event->id)
                ->with(['answers:id,skb_exam_attempt_id'])
                ->orderBy('id')
                ->get()
                ->keyBy('user_id')
            : collect();

        return $participants->map(function (EventParticipant $participant, int $index) use ($includesSkd, $includesSkb, $skdAttemptsByUser, $skbAttemptsByUser) {
            $row = [
                $index + 1,
                $participant->name,
                $participant->user?->nip ?: $participant->nik,
                $participant->jabatan_label,
                $participant->eventSession?->name ?? '',
            ];

            if ($includesSkd) {
                $row = array_merge($row, $this->skdColumns($skdAttemptsByUser->get($participant->user_id)));
            }

            if ($includesSkb) {
                $row = array_merge($row, $this->skbColumns($skbAttemptsByUser->get($participant->user_id)));
            }

            return $row;
        });
    }

    private function skdColumns(?ExamAttempt $attempt): array
    {
        if ($attempt === null) {
            return [0, 0, 0, 0, 0, 0, 'Belum Mulai', '', ''];
        }

        $total = $attempt->answers->count();
        $answered = $attempt->answers->whereNotNull('selected_option_id')->count();

        if ($attempt->status === ExamAttemptStatus::InProgress) {
            $scores = $attempt->calculateScores();
            [$twk, $tiu, $tkp, $totalScore] = [$scores['twk'], $scores['tiu'], $scores['tkp'], $scores['total']];
        } else {
            $twk = (int) $attempt->score_twk;
            $tiu = (int) $attempt->score_tiu;
            $tkp = (int) $attempt->score_tkp;
            $totalScore = (int) $attempt->total_score;
        }

        return [
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
    }

    private function skbColumns(?SkbExamAttempt $attempt): array
    {
        if ($attempt === null) {
            return [0, 0, 0, 'Belum Mulai', '', ''];
        }

        $isFinal = $attempt->status !== ExamAttemptStatus::InProgress;

        return [
            $isFinal ? (int) $attempt->correct_count : 0,
            $attempt->answers->count(),
            $isFinal ? (int) $attempt->total_score : 0,
            $attempt->status->label(),
            $attempt->started_at?->format('d/m/Y H:i') ?? '',
            $attempt->submitted_at?->format('d/m/Y H:i') ?? '',
        ];
    }
}
