<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use Illuminate\Support\Collection;

/**
 * Exam results for an offline event, one exam type at a time. The "Hasil
 * Ujian" page and its Excel export both read from here so the numbers on
 * screen and in the file can never drift apart.
 */
class EventResultsService
{
    public function __construct(
        private readonly ExamService $examService,
        private readonly SkbExamService $skbExamService,
    ) {}

    /**
     * Exam types this event actually runs: plain offline events are SKD
     * only; Mode Ujian events follow their exam_mode.
     *
     * @return list<string> subset of ['skd', 'skb']
     */
    public function availableTypes(Event $event): array
    {
        if (! $event->is_mode_ujian) {
            return ['skd'];
        }

        return array_values(array_filter([
            $event->exam_mode->includesSkd() ? 'skd' : null,
            $event->exam_mode->includesSkb() ? 'skb' : null,
        ]));
    }

    /**
     * @return Collection<int, array<string, mixed>> ranked by score, highest first
     */
    public function rows(Event $event, string $type, ?int $sessionId = null): Collection
    {
        $rows = $type === 'skb'
            ? $this->skbRows($event, $sessionId)
            : $this->skdRows($event, $sessionId);

        return $rows
            ->sortBy([['score', 'desc'], ['name', 'asc']])
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });
    }

    private function skdRows(Event $event, ?int $sessionId): Collection
    {
        $expired = ExamAttempt::query()
            ->where('event_id', $event->id)
            ->when($sessionId, fn ($query) => $query->where('event_session_id', $sessionId))
            ->expiredButOpen()
            ->get();

        if ($expired->isNotEmpty()) {
            $this->examService->finalizeExpiredAttempts($expired);
        }

        $attempts = ExamAttempt::query()
            ->where('event_id', $event->id)
            ->when($sessionId, fn ($query) => $query->where('event_session_id', $sessionId))
            ->with([
                'user:id,name,nip,nik,instansi_id',
                'user.instansi:id,nama',
                'eventSession:id,name',
                'answers:id,exam_attempt_id,question_id,selected_option_id',
                'answers.selectedOption:id,question_id,score_weight,is_correct',
                'answers.question:id,subject_id',
                'answers.question.subject:id,code',
            ])
            ->orderBy('id')
            ->get();

        // Plain offline events have no roster — whoever joined is the list.
        if (! $event->is_mode_ujian) {
            return $attempts->map(fn (ExamAttempt $attempt) => $this->skdRow(
                name: $attempt->resolvedDisplayName(),
                identifier: $attempt->user?->nip ?: $attempt->user?->nik,
                unit: $attempt->user?->instansi?->nama,
                session: $attempt->eventSession?->name,
                attempt: $attempt,
            ));
        }

        // Mode Ujian: the registered roster is the list, so a peserta who
        // never started still appears (with zeros) instead of vanishing.
        $attemptsByUser = $attempts->keyBy('user_id');

        return $this->participants($event, $sessionId)->map(fn (EventParticipant $participant) => $this->skdRow(
            name: $participant->name,
            identifier: $participant->user?->nip ?: $participant->nik,
            unit: $participant->jabatan_label,
            session: $participant->eventSession?->name,
            attempt: $attemptsByUser->get($participant->user_id),
        ));
    }

    private function skdRow(string $name, ?string $identifier, ?string $unit, ?string $session, ?ExamAttempt $attempt): array
    {
        $row = [
            'name' => $name,
            'identifier' => (string) $identifier,
            'unit' => (string) $unit,
            'session' => (string) $session,
            'answered' => 0,
            'total' => 0,
            'twk' => 0,
            'tiu' => 0,
            'tkp' => 0,
            'score' => 0,
            'status' => 'Belum Mulai',
            'started_at' => '',
            'submitted_at' => '',
        ];

        if ($attempt === null) {
            return $row;
        }

        $inProgress = $attempt->status === ExamAttemptStatus::InProgress;
        $scores = $inProgress
            ? $attempt->calculateScores()
            : [
                'twk' => (int) $attempt->score_twk,
                'tiu' => (int) $attempt->score_tiu,
                'tkp' => (int) $attempt->score_tkp,
                'total' => (int) $attempt->total_score,
            ];

        return array_merge($row, [
            'answered' => $attempt->answers->whereNotNull('selected_option_id')->count(),
            'total' => $attempt->answers->count(),
            'twk' => (int) $scores['twk'],
            'tiu' => (int) $scores['tiu'],
            'tkp' => (int) $scores['tkp'],
            'score' => (int) $scores['total'],
            'status' => $inProgress ? 'Sedang Ujian' : 'Selesai',
            'started_at' => $attempt->started_at?->format('d/m/Y H:i') ?? '',
            'submitted_at' => $attempt->submitted_at?->format('d/m/Y H:i') ?? '',
        ]);
    }

    private function skbRows(Event $event, ?int $sessionId): Collection
    {
        $expired = SkbExamAttempt::query()
            ->where('event_id', $event->id)
            ->when($sessionId, fn ($query) => $query->where('event_session_id', $sessionId))
            ->expiredButOpen()
            ->get();

        if ($expired->isNotEmpty()) {
            $this->skbExamService->finalizeExpiredAttempts($expired);
        }

        $attempts = SkbExamAttempt::query()
            ->where('event_id', $event->id)
            ->when($sessionId, fn ($query) => $query->where('event_session_id', $sessionId))
            ->with('answers:id,skb_exam_attempt_id,selected_option_id')
            ->orderBy('id')
            ->get();

        $live = $this->skbExamService->liveScores($attempts);
        $attemptsByUser = $attempts->keyBy('user_id');

        return $this->participants($event, $sessionId)->map(function (EventParticipant $participant) use ($attemptsByUser, $live) {
            $attempt = $attemptsByUser->get($participant->user_id);

            $row = [
                'name' => $participant->name,
                'identifier' => (string) ($participant->user?->nip ?: $participant->nik),
                'unit' => (string) ($participant->jabatanSkb?->name ?? $participant->jabatan_label),
                'session' => (string) $participant->eventSession?->name,
                'answered' => 0,
                'total' => 0,
                'benar' => 0,
                'score' => 0,
                'status' => 'Belum Mulai',
                'started_at' => '',
                'submitted_at' => '',
            ];

            if ($attempt === null) {
                return $row;
            }

            return array_merge($row, [
                'answered' => $attempt->answers->whereNotNull('selected_option_id')->count(),
                'total' => $attempt->answers->count(),
                'benar' => $live[$attempt->id]['benar'] ?? 0,
                'score' => $live[$attempt->id]['score'] ?? 0,
                'status' => $attempt->status === ExamAttemptStatus::InProgress ? 'Sedang Ujian' : 'Selesai',
                'started_at' => $attempt->started_at?->format('d/m/Y H:i') ?? '',
                'submitted_at' => $attempt->submitted_at?->format('d/m/Y H:i') ?? '',
            ]);
        });
    }

    /** @return Collection<int, EventParticipant> */
    private function participants(Event $event, ?int $sessionId): Collection
    {
        return EventParticipant::query()
            ->where('event_id', $event->id)
            ->when($sessionId, fn ($query) => $query->where('event_session_id', $sessionId))
            ->with(['user:id,nip', 'eventSession:id,name', 'jabatanSkb:id,name'])
            ->get();
    }
}
