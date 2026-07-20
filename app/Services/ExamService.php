<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Jobs\GenerateExamPsychologyReportJob;
use App\Models\CoinTransaction;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\XpReward;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExamService
{
    private const REMEDIAL_TIME_MULTIPLIER = 1.2;

    private const REMEDIAL_MIN_SECONDS_PER_QUESTION = 65;

    private const REMEDIAL_MIN_TOTAL_MINUTES = 3;

    public function findActiveFullAttempt(User $user): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->where('attempt_type', ExamAttemptType::Full)
            ->whereNull('duel_session_id')
            ->with('exam')
            ->latest('id')
            ->get()
            ->first(fn (ExamAttempt $attempt) => $attempt->isActive() && ! $attempt->isDuelAttempt());
    }

    public function startAttempt(Exam $exam, User $user, ?int $eventId = null, ?int $eventSessionId = null): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user, $eventId, $eventSessionId) {
            $generator = app(ExamQuestionGeneratorService::class);
            $difficulty = $exam->settings['difficulty'] ?? 'all';

            try {
                $generator->assertSufficientQuestions($difficulty);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'exam' => 'Bank soal tidak cukup untuk memulai ujian. Hubungi admin.',
                ]);
            }

            $attempt = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'event_id' => $eventId,
                'event_session_id' => $eventSessionId,
                'user_id' => $user->id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($exam->duration_minutes),
                'status' => ExamAttemptStatus::InProgress,
            ]);

            foreach ($generator->generate($difficulty) as $item) {
                ExamAnswer::query()->create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $item['id'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            return $attempt->load(['answers.question.options', 'answers.question.subject']);
        });
    }

    /**
     * Restart an attempt from scratch, keeping the same attempt row so a
     * participant still occupies exactly one slot on the livescore.
     *
     * Used by event organisers when a participant is stuck — e.g. a clock/time
     * zone mismatch expired their exam and extending time is no longer possible.
     */
    public function resetAttempt(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = ExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->with('exam')
                ->firstOrFail();

            $exam = $attempt->exam;

            if ($exam === null) {
                throw ValidationException::withMessages([
                    'reset' => 'Paket ujian tidak ditemukan untuk percobaan ini.',
                ]);
            }

            $generator = app(ExamQuestionGeneratorService::class);
            $difficulty = $exam->settings['difficulty'] ?? 'all';

            try {
                $generator->assertSufficientQuestions($difficulty);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'reset' => 'Bank soal tidak cukup untuk mengulang ujian.',
                ]);
            }

            // Drop everything produced by the previous run.
            $attempt->answers()->delete();
            $attempt->telemetries()->delete();

            XpReward::query()
                ->where('source_type', ExamAttempt::class)
                ->where('source_id', $attempt->id)
                ->delete();

            CoinTransaction::query()
                ->where('source_type', ExamAttempt::class)
                ->where('source_id', $attempt->id)
                ->delete();

            $attempt->update([
                'started_at' => now(),
                'expires_at' => now()->addMinutes($exam->duration_minutes),
                'submitted_at' => null,
                'status' => ExamAttemptStatus::InProgress,
                'score_twk' => null,
                'score_tiu' => null,
                'score_tkp' => null,
                'total_score' => null,
                'question_duration' => null,
                'answer_behavior' => null,
                'help_items_state' => null,
                'psychology_report' => null,
                'psychology_report_status' => 'skipped', // column default — not nullable
                'psychology_report_generated_at' => null,
            ]);

            // Fresh randomised question set, exactly like a brand new attempt.
            foreach ($generator->generate($difficulty) as $item) {
                ExamAnswer::query()->create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $item['id'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            return $attempt->fresh();
        });
    }

    public function startRemedialAttempt(ExamAttempt $parentAttempt, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($parentAttempt, $user) {
            $parentAttempt = ExamAttempt::query()
                ->whereKey($parentAttempt->id)
                ->lockForUpdate()
                ->with(['exam', 'answers.question', 'answers.selectedOption'])
                ->firstOrFail();

            if ($parentAttempt->user_id !== $user->id) {
                throw new AccessDeniedHttpException('Attempt tidak dimiliki oleh pengguna ini.');
            }

            if (! $parentAttempt->isFull()) {
                throw ValidationException::withMessages([
                    'remedial' => 'Ujian remedial hanya bisa dimulai dari simulasi penuh.',
                ]);
            }

            if (! $parentAttempt->isReviewable()) {
                throw ValidationException::withMessages([
                    'remedial' => 'Simulasi belum selesai. Tidak bisa memulai remedial.',
                ]);
            }

            if ($parentAttempt->isDuelAttempt()) {
                throw ValidationException::withMessages([
                    'remedial' => 'Ujian remedial tidak tersedia untuk duel mini tryout.',
                ]);
            }

            $gamification = app(GamificationService::class);

            if (! $gamification->isRemedialUnlocked($gamification->totalXp($user))) {
                throw ValidationException::withMessages([
                    'remedial' => 'Fitur belum terbuka. Kumpulkan '.GamificationService::REMEDIAL_UNLOCK_XP.' XP terlebih dahulu.',
                ]);
            }

            $wrongAnswers = $parentAttempt->answers
                ->filter(fn (ExamAnswer $answer) => $answer->question && ! $answer->reviewOutcome()->isPositive())
                ->sortBy(fn (ExamAnswer $answer) => $answer->sort_order ?: 999)
                ->values();

            if ($wrongAnswers->isEmpty()) {
                throw ValidationException::withMessages([
                    'remedial' => 'Tidak ada soal salah. Semua jawaban sudah benar.',
                ]);
            }

            $existingAttempt = ExamAttempt::query()
                ->where('exam_id', $parentAttempt->exam_id)
                ->where('user_id', $user->id)
                ->where('status', ExamAttemptStatus::InProgress)
                ->first();

            if ($existingAttempt?->isActive()) {
                throw ValidationException::withMessages([
                    'remedial' => 'Anda masih memiliki ujian yang berjalan. Selesaikan terlebih dahulu.',
                ]);
            }

            $exam = $parentAttempt->exam;
            $parentQuestionCount = $parentAttempt->answers->count();
            $durationMinutes = $this->remedialDurationMinutes($exam, $wrongAnswers->count(), $parentQuestionCount);

            $attempt = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'attempt_type' => ExamAttemptType::Remedial,
                'parent_attempt_id' => $parentAttempt->id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($durationMinutes),
                'status' => ExamAttemptStatus::InProgress,
            ]);

            foreach ($wrongAnswers->values() as $index => $answer) {
                ExamAnswer::query()->create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $answer->question_id,
                    'sort_order' => $index + 1,
                ]);
            }

            return $attempt->load(['answers.question.options', 'answers.question.subject']);
        });
    }

    public function remedialDurationMinutes(Exam $exam, int $wrongCount, ?int $parentQuestionCount = null): int
    {
        if ($wrongCount <= 0) {
            return self::REMEDIAL_MIN_TOTAL_MINUTES;
        }

        $parentTotal = max(1, $parentQuestionCount ?? ExamQuestionGeneratorService::TOTAL_QUESTIONS);
        $secondsPerQuestion = ($exam->duration_minutes * 60) / $parentTotal;

        $totalSeconds = (int) ceil(
            $secondsPerQuestion * $wrongCount * self::REMEDIAL_TIME_MULTIPLIER,
        );

        $totalSeconds = max($totalSeconds, $wrongCount * self::REMEDIAL_MIN_SECONDS_PER_QUESTION);

        return max(self::REMEDIAL_MIN_TOTAL_MINUTES, (int) ceil($totalSeconds / 60));
    }

    public function submitAttempt(ExamAttempt $attempt, ?User $user = null): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $user) {
            $attempt = ExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user !== null && $attempt->user_id !== $user->id) {
                throw new AccessDeniedHttpException('Attempt tidak dimiliki oleh pengguna ini.');
            }

            if ($attempt->status !== ExamAttemptStatus::InProgress) {
                return $attempt->fresh();
            }

            $attempt->load(['answers.selectedOption', 'answers.question.subject']);

            $scores = $attempt->calculateScores();

            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'score_twk' => $scores['twk'],
                'score_tiu' => $scores['tiu'],
                'score_tkp' => $scores['tkp'],
                'total_score' => $scores['total'],
            ]);

            $gamification = app(GamificationService::class);
            $rewardUser = $user ?? User::query()->find($attempt->user_id);
            $xpBefore = $rewardUser !== null ? $gamification->totalXp($rewardUser) : 0;

            if ($attempt->isRemedial()) {
                $attempt->update([
                    'psychology_report_status' => 'skipped',
                    'psychology_report_generated_at' => now(),
                ]);

                if ($rewardUser !== null) {
                    $gamification->awardRemedialPerfectXp($attempt, $rewardUser);
                }
            } else {
                app(ExamWeaknessAnalysisService::class)->forget($attempt->user_id);

                if ($rewardUser !== null) {
                    $gamification->awardExamAttemptXp($attempt, $rewardUser);
                    app(CoinService::class)->awardExamAttemptCoins($attempt, $rewardUser);
                }

                $attempt->update(['psychology_report_status' => 'pending']);
                GenerateExamPsychologyReportJob::dispatch($attempt->id);
            }

            if ($rewardUser !== null) {
                $xpAfter = $gamification->totalXp($rewardUser);

                if ($gamification->crossedRemedialUnlockThreshold($xpBefore, $xpAfter)) {
                    session()->flash('show_remedial_unlock_modal', true);
                }
            }

            return $attempt->fresh();
        });
    }
}
