<?php

namespace App\Exports;

use App\Data\ExamResultsExportFilters;
use App\Models\ExamAttempt;
use App\Support\ExamResultsQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExamResultsSummaryExport implements FromQuery, WithCustomCsvSettings, WithHeadings, WithMapping
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly ExamResultsExportFilters $filters,
    ) {}

    public function query(): Builder
    {
        return ExamResultsQuery::filtered($this->filters)
            ->with(['user.instansi', 'exam', 'duelSession'])
            ->orderBy('submitted_at');
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Email',
            'Username',
            'NIP',
            'Instansi',
            'Judul Ujian',
            'Jenis Ujian',
            'TWK',
            'TIU',
            'TKP',
            'Total',
            'Status',
            'Waktu Mulai',
            'Waktu Selesai',
        ];
    }

    /**
     * @param  ExamAttempt  $row
     */
    public function map($row): array
    {
        $this->rowNumber++;

        $isDuel = $row->isDuelAttempt();
        $isRemedial = $row->isRemedial();

        return [
            $this->rowNumber,
            $row->user?->name ?? '',
            $row->user?->email ?? '',
            $row->user?->username ?? '',
            $row->user?->nip ?? '',
            $row->user?->instansi?->nama ?? '',
            $row->exam?->title ?? '',
            $this->resolveExamTypeLabel($isDuel, $isRemedial),
            (int) $row->score_twk,
            (int) $row->score_tiu,
            (int) $row->score_tkp,
            (int) $row->total_score,
            $this->resolveStatusLabel($row, $isDuel),
            $row->started_at?->format('d/m/Y H:i') ?? '',
            $row->submitted_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
        ];
    }

    private function resolveExamTypeLabel(bool $isDuel, bool $isRemedial): string
    {
        if ($isRemedial) {
            return 'Ujian Remedial';
        }

        if ($isDuel) {
            return 'Duel Mini-Tryout';
        }

        return 'Simulasi';
    }

    private function resolveStatusLabel(ExamAttempt $row, bool $isDuel): string
    {
        if ($isDuel) {
            if ($row->duelSession?->winner_user_id === null) {
                return 'Seri';
            }

            return $row->duelSession->winner_user_id === $row->user_id ? 'Menang' : 'Kalah';
        }

        return exam_attempt_passes(
            $row->score_twk,
            $row->score_tiu,
            $row->score_tkp,
            $row->total_score,
        ) ? 'Lulus' : 'Belum Lulus';
    }
}
