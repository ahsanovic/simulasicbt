<?php

namespace App\Support;

use App\Enums\ExamAttemptType;
use App\Models\ExamAttempt;

final class ExamResultsCsvMapper
{
    private int $rowNumber = 0;

    /** @return list<string> */
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

    /** @return list<int|string> */
    public function map(ExamAttempt $attempt): array
    {
        $this->rowNumber++;

        $isDuel = $this->isDuelAttempt($attempt);
        $isRemedial = $attempt->attempt_type === ExamAttemptType::Remedial;

        return [
            $this->rowNumber,
            $attempt->user?->name ?? '',
            $attempt->user?->email ?? '',
            $attempt->user?->username ?? '',
            $attempt->user?->nip ?? '',
            $attempt->user?->instansi?->nama ?? '',
            $attempt->exam?->title ?? '',
            $this->resolveExamTypeLabel($isDuel, $isRemedial),
            (int) $attempt->score_twk,
            (int) $attempt->score_tiu,
            (int) $attempt->score_tkp,
            (int) $attempt->total_score,
            $this->resolveStatusLabel($attempt, $isDuel),
            $attempt->started_at?->format('d/m/Y H:i') ?? '',
            $attempt->submitted_at?->format('d/m/Y H:i') ?? '',
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

    private function resolveStatusLabel(ExamAttempt $attempt, bool $isDuel): string
    {
        if ($isDuel) {
            if ($attempt->duelSession?->winner_user_id === null) {
                return 'Seri';
            }

            return $attempt->duelSession->winner_user_id === $attempt->user_id ? 'Menang' : 'Kalah';
        }

        return exam_attempt_passes(
            $attempt->score_twk,
            $attempt->score_tiu,
            $attempt->score_tkp,
            $attempt->total_score,
        ) ? 'Lulus' : 'Belum Lulus';
    }

    private function isDuelAttempt(ExamAttempt $attempt): bool
    {
        if ($attempt->duel_session_id !== null) {
            return true;
        }

        return (bool) ($attempt->exam?->settings['is_duel'] ?? false);
    }
}
