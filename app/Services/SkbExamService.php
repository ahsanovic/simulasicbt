<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
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

            foreach ($questionIds->values() as $index => $questionId) {
                SkbExamAnswer::query()->create([
                    'skb_exam_attempt_id' => $attempt->id,
                    'skb_question_id' => $questionId,
                    'sort_order' => $index + 1,
                ]);
            }

            return $attempt->load(['answers.question.options']);
        });
    }

    /**
     * Persist a peserta-confirmed answer. Refuses (returns false) once the
     * attempt is no longer in progress or its time is up, so answers cannot
     * change after submission/expiry.
     */
    public function saveAnswer(SkbExamAttempt $attempt, int $questionId, ?int $selectedOptionId): bool
    {
        if (! $attempt->isActive()) {
            return false;
        }

        $attempt->answers()
            ->where('skb_question_id', $questionId)
            ->update(['selected_option_id' => $selectedOptionId]);

        return true;
    }

    public function toggleMark(SkbExamAttempt $attempt, int $questionId): void
    {
        $answer = $attempt->answers()->where('skb_question_id', $questionId)->first();
        $answer?->update(['is_marked' => ! $answer->is_marked]);
    }

    public function submitAttempt(SkbExamAttempt $attempt): SkbExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt->load('answers.question.options');

            $correctCount = 0;

            foreach ($attempt->answers as $answer) {
                $correctOption = $answer->question->correctOption();
                $isCorrect = $answer->selected_option_id !== null
                    && $correctOption !== null
                    && $answer->selected_option_id === $correctOption->id;

                $answer->update(['is_correct' => $isCorrect]);

                if ($isCorrect) {
                    $correctCount++;
                }
            }

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
        return DB::transaction(function () use ($attempt) {
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
    }
}
