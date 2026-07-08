<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Jobs\GenerateExamPsychologyReportJob;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExamService
{
    public function startAttempt(Exam $exam, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user) {
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

            app(ExamWeaknessAnalysisService::class)->forget($attempt->user_id);

            $rewardUser = $user ?? User::query()->find($attempt->user_id);

            if ($rewardUser !== null) {
                app(GamificationService::class)->awardExamAttemptXp($attempt, $rewardUser);
            }

            $attempt->update(['psychology_report_status' => 'pending']);
            GenerateExamPsychologyReportJob::dispatch($attempt->id);

            return $attempt->fresh();
        });
    }
}
