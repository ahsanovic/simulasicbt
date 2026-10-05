<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Support\ExamDeadline;
use App\Support\LiveScoreCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkbExamService
{
    public function findActiveAttempt(Event $event, int $userId): ?SkbExamAttempt
    {
        return SkbExamAttempt::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::InProgress)
            ->latest('id')
            ->first();
    }

    public function findLatestAttempt(Event $event, int $userId): ?SkbExamAttempt
    {
        return SkbExamAttempt::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->latest('id')
            ->first();
    }

    public function startAttempt(Event $event, EventParticipant $participant): SkbExamAttempt
    {
        if ($participant->jabatan_skb_id === null) {
            throw ValidationException::withMessages([
                'jabatan' => 'Jabatan SKB peserta tidak ditemukan. Hubungi admin.',
            ]);
        }

        $questionCount = (int) $event->skb_question_count;

        $questionIds = SkbQuestion::query()
            ->where('jabatan_skb_id', $participant->jabatan_skb_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($questionCount)
            ->pluck('id');

        if ($questionIds->count() < $questionCount) {
            throw ValidationException::withMessages([
                'jabatan' => "Bank soal SKB jabatan ini hanya punya {$questionIds->count()} soal aktif, butuh {$questionCount}. Hubungi admin.",
            ]);
        }

        return DB::transaction(function () use ($event, $participant, $questionIds) {
            $attempt = SkbExamAttempt::query()->create([
                'event_id' => $event->id,
                'event_session_id' => $participant->event_session_id,
                'event_participant_id' => $participant->id,
                'user_id' => $participant->user_id,
                'jabatan_skb_id' => $participant->jabatan_skb_id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes((int) $event->skb_duration_minutes),
                'status' => ExamAttemptStatus::InProgress,
                'correct_score' => (int) $event->skb_correct_score,
            ]);

            // One multi-row insert instead of one query per question (see ExamService::startAttempt).
            $now = now();
            SkbExamAnswer::query()->insert($questionIds->values()
                ->map(fn ($questionId, $index) => [
                    'skb_exam_attempt_id' => $attempt->id,
                    'skb_question_id' => $questionId,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());

            return $attempt;
        });
    }

    /**
     * Persist a peserta-confirmed answer. Refuses once the attempt is no
     * longer in progress or its time is up, so answers cannot change after
     * submission/expiry.
     *
     * @return bool|null true = saved, false = refused (attempt closed / past
     *                   the deadline), null = ignored as stale (a newer
     *                   versioned save already landed)
     */
    public function saveAnswer(SkbExamAttempt $attempt, int $questionId, ?int $selectedOptionId, ?int $version = null): ?bool
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress || ! ExamDeadline::acceptsAnswers($attempt->expires_at)) {
            return false;
        }

        // With a version (X-Exam-Seq) only a newer save may overwrite the
        // stored one; an older request arriving late changes nothing.
        $written = $attempt->answers()
            ->where('skb_question_id', $questionId)
            ->when($version !== null, fn ($query) => $query->where('answer_version', '<', $version))
            ->update([
                'selected_option_id' => $selectedOptionId,
                ...($version !== null ? ['answer_version' => $version] : []),
            ]);

        return $version !== null && $written === 0 ? null : true;
    }

    /**
     * Live "benar" / score per attempt for the livescore boards.
     *
     * Submitted/expired attempts use the stored correct_count/total_score.
     * In-progress attempts are scored on the fly from the answers saved so
     * far (a saved answer counts once its option is the correct one), so the
     * board moves while the peserta is still working instead of sitting at 0
     * until submit. One query for all correct options, no N+1.
     *
     * @param  iterable<SkbExamAttempt>  $attempts  answers must be loaded (selected_option_id)
     * @return array<int, array{benar: int, score: int}> keyed by attempt id
     */
    public function liveScores(iterable $attempts): array
    {
        $attempts = collect($attempts);

        $selectedOptionIds = $attempts
            ->filter(fn (SkbExamAttempt $attempt) => $attempt->status === ExamAttemptStatus::InProgress)
            ->flatMap(fn (SkbExamAttempt $attempt) => $attempt->answers->pluck('selected_option_id'))
            ->filter()
            ->unique()
            ->values();

        $correctOptionIds = $selectedOptionIds->isEmpty()
            ? []
            : SkbQuestionOption::query()
                ->whereIn('id', $selectedOptionIds)
                ->where('is_correct', true)
                ->pluck('id')
                ->flip()
                ->all();

        return $attempts->mapWithKeys(function (SkbExamAttempt $attempt) use ($correctOptionIds) {
            if ($attempt->status !== ExamAttemptStatus::InProgress) {
                return [$attempt->id => [
                    'benar' => (int) $attempt->correct_count,
                    'score' => (int) $attempt->total_score,
                ]];
            }

            $benar = $attempt->answers
                ->filter(fn ($answer) => $answer->selected_option_id !== null
                    && isset($correctOptionIds[$answer->selected_option_id]))
                ->count();

            return [$attempt->id => [
                'benar' => $benar,
                'score' => $benar * (int) $attempt->correct_score,
            ]];
        })->all();
    }

    public function toggleMark(SkbExamAttempt $attempt, int $questionId): void
    {
        $answer = $attempt->answers()->where('skb_question_id', $questionId)->first();
        $answer?->update(['is_marked' => ! $answer->is_marked]);
    }

    public function submitAttempt(SkbExamAttempt $attempt): SkbExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            // The poll and the browser's time-up call can both land at the
            // deadline: lock the row so only the first one scores it.
            $attempt = SkbExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($attempt->status !== ExamAttemptStatus::InProgress) {
                return $attempt;
            }

            // Mark every answer in one statement instead of one UPDATE per
            // answer (50+ queries inside this locked transaction): at the
            // deadline the whole room submits in the same few seconds.
            // Same rule as SkbQuestion::correctOption(): the first option
            // flagged correct, in option order.
            $correctOption = SkbQuestionOption::query()
                ->select('skb_question_options.id')
                ->whereColumn('skb_question_options.skb_question_id', 'skb_exam_answers.skb_question_id')
                ->where('skb_question_options.is_correct', true)
                ->orderBy('skb_question_options.sort_order')
                ->orderBy('skb_question_options.id')
                ->limit(1);

            SkbExamAnswer::query()
                ->where('skb_exam_attempt_id', $attempt->id)
                ->update([
                    'is_correct' => DB::raw('CASE WHEN skb_exam_answers.selected_option_id IS NOT NULL AND skb_exam_answers.selected_option_id = ('.$correctOption->toRawSql().') THEN 1 ELSE 0 END'),
                    'updated_at' => now(),
                ]);

            $correctCount = SkbExamAnswer::query()
                ->where('skb_exam_attempt_id', $attempt->id)
                ->where('is_correct', true)
                ->count();

            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'correct_count' => $correctCount,
                'total_score' => $correctCount * $attempt->correct_score,
            ]);

            return $attempt->fresh();
        });
    }

    /**
     * Close out attempts whose time is already up but that were never submitted —
     * mirrors ExamService::finalizeExpiredAttempts() for the SKB attempt table.
     *
     * @param  iterable<SkbExamAttempt>  $attempts
     */
    public function finalizeExpiredAttempts(iterable $attempts): int
    {
        $closed = 0;

        foreach ($attempts as $attempt) {
            if ($attempt->status !== ExamAttemptStatus::InProgress || $attempt->expires_at->isFuture()) {
                continue;
            }

            $expiredAt = $attempt->expires_at;

            $this->submitAttempt($attempt);

            SkbExamAttempt::query()
                ->whereKey($attempt->id)
                ->update(['submitted_at' => $expiredAt]);

            $closed++;
        }

        return $closed;
    }

    /**
     * Restart an attempt from scratch, keeping the same assigned question set
     * (drawn once at start) and the same attempt row so the participant still
     * occupies exactly one slot on the livescore.
     */
    public function resetAttempt(SkbExamAttempt $attempt): SkbExamAttempt
    {
        $attempt = DB::transaction(function () use ($attempt) {
            $attempt = SkbExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->with('event')
                ->firstOrFail();

            $attempt->answers()->update(['selected_option_id' => null, 'is_correct' => null]);

            $attempt->update([
                'status' => ExamAttemptStatus::InProgress,
                'started_at' => now(),
                'expires_at' => now()->addMinutes((int) $attempt->event->skb_duration_minutes),
                'submitted_at' => null,
                'correct_count' => null,
                'total_score' => null,
            ]);

            return $attempt->fresh();
        });

        // The board shows the restarted attempt straight away.
        if ($attempt->event_id !== null) {
            LiveScoreCache::bust($attempt->event_id);
        }

        return $attempt;
    }
}
