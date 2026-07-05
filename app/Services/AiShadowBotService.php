<?php

namespace App\Services;

use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\UserRole;
use App\Models\DuelSession;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiShadowBotService
{
    public const BOT_EMAIL = 'ai-shadow-bot@system.local';

    public function botUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::BOT_EMAIL],
            [
                'name' => 'AI Shadow Bot',
                'username' => 'ai-shadow-bot',
                'password' => bcrypt(str()->random(32)),
                'role' => UserRole::Peserta,
                'is_active' => true,
                'is_pegawai' => false,
            ],
        );
    }

    public function initializeBotAttempt(DuelSession $session): void
    {
        if (! $session->is_bot_opponent || $session->opponent_attempt_id) {
            return;
        }

        DB::transaction(function () use ($session) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->opponent_attempt_id) {
                return;
            }

            $bot = $this->botUser();
            $attempt = $this->createBotAttempt($session, $bot);
            $session->update(['opponent_attempt_id' => $attempt->id]);
        });
    }

    public function advanceProgress(DuelSession $session): void
    {
        if (! $session->is_bot_opponent || $session->status !== DuelSessionStatus::InProgress) {
            return;
        }

        $session = $session->fresh();

        if ($session->opponent_finished_at) {
            return;
        }

        $targetProgress = $this->calculateBotTargetProgress($session);

        if ($targetProgress <= $session->opponent_progress) {
            return;
        }

        $this->answerBotQuestionsUpTo($session, $targetProgress);

        DuelSession::query()
            ->whereKey($session->id)
            ->update(['opponent_progress' => $targetProgress]);

        if ($targetProgress >= DuelSession::TOTAL_QUESTIONS) {
            $this->completeBotAttempt($session->fresh());
        }
    }

    public function completeBotAttempt(DuelSession $session): void
    {
        if (! $session->is_bot_opponent || ! $session->opponent_attempt_id) {
            return;
        }

        DB::transaction(function () use ($session) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $attempt = ExamAttempt::query()->whereKey($session->opponent_attempt_id)->lockForUpdate()->first();

            if (! $attempt || $attempt->status !== ExamAttemptStatus::InProgress) {
                return;
            }

            $this->answerBotQuestionsUpTo($session, DuelSession::TOTAL_QUESTIONS);

            $attempt = $attempt->fresh(['answers.selectedOption', 'answers.question.subject']);
            $scores = $attempt->calculateScores();

            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'score_twk' => $scores['twk'],
                'score_tiu' => $scores['tiu'],
                'score_tkp' => $scores['tkp'],
                'total_score' => $scores['total'],
            ]);

            $session->update([
                'opponent_progress' => DuelSession::TOTAL_QUESTIONS,
                'opponent_finished_at' => now(),
            ]);
        });
    }

    private function createBotAttempt(DuelSession $session, User $bot): ExamAttempt
    {
        $exam = ExamAttempt::query()
            ->where('duel_session_id', $session->id)
            ->value('exam_id');

        $generator = app(DuelQuestionGeneratorService::class);
        $items = $generator->toQuestionItems($session->question_ids);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam,
            'duel_session_id' => $session->id,
            'user_id' => $bot->id,
            'started_at' => $session->started_at ?? now(),
            'expires_at' => $session->expires_at,
            'status' => ExamAttemptStatus::InProgress,
        ]);

        foreach ($items as $item) {
            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $item['id'],
                'sort_order' => $item['sort_order'],
            ]);
        }

        return $attempt;
    }

    private function answerBotQuestionsUpTo(DuelSession $session, int $upToQuestion): void
    {
        $attempt = ExamAttempt::query()
            ->whereKey($session->opponent_attempt_id)
            ->with(['answers.question.options', 'answers.question.subject'])
            ->first();

        if (! $attempt) {
            return;
        }

        $accuracy = $this->botAccuracyFor($session->host_user_id);

        foreach ($attempt->answers->sortBy('sort_order') as $answer) {
            if ($answer->sort_order > $upToQuestion) {
                break;
            }

            if ($answer->selected_option_id) {
                continue;
            }

            $optionId = $this->pickOptionForAnswer($answer, $accuracy);

            ExamAnswer::query()
                ->whereKey($answer->id)
                ->update([
                    'selected_option_id' => $optionId,
                    'answered_at' => now(),
                ]);
        }
    }

    private function pickOptionForAnswer(ExamAnswer $answer, float $accuracy): int
    {
        $options = $answer->question->options;
        $code = $answer->question->subject->code->value;

        if ($code === 'tkp') {
            $sorted = $options->sortByDesc('score_weight');

            return (random_int(1, 100) / 100) <= $accuracy
                ? $sorted->first()->id
                : $sorted->skip(1)->random()->id;
        }

        $correct = $options->firstWhere('is_correct', true);

        if ((random_int(1, 100) / 100) <= $accuracy && $correct) {
            return $correct->id;
        }

        $wrong = $options->where('is_correct', false);

        return $wrong->isNotEmpty() ? $wrong->random()->id : $options->first()->id;
    }

    private function botAccuracyFor(int $hostUserId): float
    {
        $avgScore = ExamAttempt::query()
            ->where('user_id', $hostUserId)
            ->where('status', ExamAttemptStatus::Submitted)
            ->whereNull('duel_session_id')
            ->whereNotNull('total_score')
            ->avg('total_score');

        if ($avgScore === null) {
            return 0.65;
        }

        $maxTotal = 75;

        return max(0.45, min(0.92, ($avgScore / $maxTotal) * 0.85 + 0.1));
    }

    private function calculateBotTargetProgress(DuelSession $session): int
    {
        if (! $session->started_at) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($session->started_at);
        $totalSeconds = $session->duration_minutes * 60;
        $avgSecondsPerQuestion = $totalSeconds / DuelSession::TOTAL_QUESTIONS;

        $hostPace = max(1, $session->host_progress);
        $timeBased = (int) floor($elapsed / max(15, $avgSecondsPerQuestion * 0.85));

        $target = (int) round(($timeBased + $hostPace) / 2);

        return max(0, min(DuelSession::TOTAL_QUESTIONS, $target));
    }
}
