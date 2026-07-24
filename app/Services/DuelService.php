<?php

namespace App\Services;

use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\UserRole;
use App\Models\DuelSession;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Notifications\DuelChallengeAccepted;
use App\Notifications\DuelChallengeReceived;
use App\Notifications\DuelChallengeRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DuelService
{
    public const MATCHMAKING_BOT_WAIT_SECONDS = 45;

    public function __construct(
        private readonly DuelQuestionGeneratorService $questionGenerator,
        private readonly AiShadowBotService $shadowBot,
        private readonly DuelPresenceService $presence,
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * Masuk antrean matchmaking. Duel hanya dimulai jika ada pemain lain
     * yang juga sedang mengantre, atau setelah timeout lawan diganti AI.
     */
    public function enterMatchmakingQueue(User $user): DuelSession
    {
        return DB::transaction(function () use ($user) {
            $ownQueue = DuelSession::query()
                ->where('host_user_id', $user->id)
                ->where('status', DuelSessionStatus::Waiting)
                ->where('match_type', DuelMatchType::Random)
                ->lockForUpdate()
                ->first();

            if ($ownQueue) {
                return $ownQueue;
            }

            $waiting = DuelSession::query()
                ->where('status', DuelSessionStatus::Waiting)
                ->where('match_type', DuelMatchType::Random)
                ->where('host_user_id', '!=', $user->id)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if ($waiting) {
                return $this->joinSession($waiting, $user);
            }

            return $this->createSession($user, DuelMatchType::Random);
        });
    }

    public function pollMatchmaking(DuelSession $session, User $user): DuelSession
    {
        return DB::transaction(function () use ($session, $user) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->status === DuelSessionStatus::InProgress) {
                return $session;
            }

            if ($session->status !== DuelSessionStatus::Waiting
                || $session->match_type !== DuelMatchType::Random
                || $session->host_user_id !== $user->id) {
                return $session;
            }

            if ($session->created_at->diffInSeconds(now()) >= self::MATCHMAKING_BOT_WAIT_SECONDS) {
                return $this->assignBotAndStart($session);
            }

            return $session;
        });
    }

    public function cancelMatchmaking(User $user): void
    {
        DuelSession::query()
            ->where('host_user_id', $user->id)
            ->where('status', DuelSessionStatus::Waiting)
            ->where('match_type', DuelMatchType::Random)
            ->delete();
    }

    public function challengeFriend(User $host, string $identifier): DuelChallengeResult
    {
        $opponent = User::query()
            ->where('role', UserRole::Peserta)
            ->where('is_active', true)
            ->where('id', '!=', $host->id)
            ->where(function ($query) use ($identifier) {
                $query->where('username', $identifier)
                    ->orWhere('nip', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if (! $opponent) {
            throw ValidationException::withMessages([
                'identifier' => 'Peserta tidak ditemukan. Gunakan username, NIP, atau email.',
            ]);
        }

        $opponentWasOnline = $this->presence->isOnline($opponent);

        $session = $this->createSession($host, DuelMatchType::Friend, $opponent->id);

        $opponent->notify(new DuelChallengeReceived($session, $host));

        return new DuelChallengeResult($session, $opponent, $opponentWasOnline);
    }

    public function acceptFriendChallenge(DuelSession $session, User $opponent): DuelSession
    {
        return DB::transaction(function () use ($session, $opponent) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->opponent_user_id !== $opponent->id) {
                throw new AccessDeniedHttpException('Anda bukan lawan dalam duel ini.');
            }

            if ($session->match_type !== DuelMatchType::Friend || $session->status !== DuelSessionStatus::Waiting) {
                throw ValidationException::withMessages([
                    'duel' => 'Tantangan duel tidak valid atau sudah tidak tersedia.',
                ]);
            }

            $session = $this->startPendingDuel($session);
            $session->host->notify(new DuelChallengeAccepted($session, $opponent));

            return $session->fresh(['host', 'opponent']);
        });
    }

    public function rejectFriendChallenge(DuelSession $session, User $opponent): void
    {
        DB::transaction(function () use ($session, $opponent) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->opponent_user_id !== $opponent->id) {
                throw new AccessDeniedHttpException('Anda bukan lawan dalam duel ini.');
            }

            if ($session->match_type !== DuelMatchType::Friend || $session->status !== DuelSessionStatus::Waiting) {
                return;
            }

            $session->update(['status' => DuelSessionStatus::Cancelled]);
            $session->host->notify(new DuelChallengeRejected($session, $opponent));
        });
    }

    public function cancelFriendChallenge(DuelSession $session, User $host): void
    {
        DuelSession::query()
            ->whereKey($session->id)
            ->where('host_user_id', $host->id)
            ->where('match_type', DuelMatchType::Friend)
            ->where('status', DuelSessionStatus::Waiting)
            ->update(['status' => DuelSessionStatus::Cancelled]);
    }

    public function joinByCode(User $user, string $code): DuelSession
    {
        $session = DuelSession::query()
            ->where('code', strtoupper(trim($code)))
            ->where('status', DuelSessionStatus::Waiting)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'code' => 'Kode duel tidak valid atau sudah tidak tersedia.',
            ]);
        }

        if ($session->host_user_id === $user->id) {
            throw ValidationException::withMessages([
                'code' => 'Anda tidak dapat bergabung ke duel sendiri.',
            ]);
        }

        return DB::transaction(fn () => $this->joinSession($session, $user));
    }

    public function createInviteCode(User $host): DuelSession
    {
        return $this->createSession($host, DuelMatchType::Code);
    }

    public function cancelInviteCode(DuelSession $session, User $host): void
    {
        DuelSession::query()
            ->whereKey($session->id)
            ->where('host_user_id', $host->id)
            ->where('match_type', DuelMatchType::Code)
            ->where('status', DuelSessionStatus::Waiting)
            ->delete();
    }

    public function startPlayerAttempt(DuelSession $session, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($session, $user) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $session->isParticipant($user->id)) {
                throw new AccessDeniedHttpException('Anda bukan peserta duel ini.');
            }

            if ($session->status !== DuelSessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'duel' => 'Duel belum dimulai atau sudah selesai.',
                ]);
            }

            $existing = $session->attemptFor($user->id);

            if ($existing) {
                return $existing->load(['answers.question.options', 'answers.question.subject', 'answers.question.material.materialGroup']);
            }

            $attempt = $this->createAttemptFromSession($session, $user);

            if ($user->id === $session->host_user_id) {
                $session->update(['host_attempt_id' => $attempt->id]);
            } else {
                $session->update(['opponent_attempt_id' => $attempt->id]);
            }

            if ($session->is_bot_opponent) {
                $this->shadowBot->initializeBotAttempt($session->fresh());
            }

            return $attempt->load(['answers.question.options', 'answers.question.subject', 'answers.question.material.materialGroup']);
        });
    }

    public function updateProgress(DuelSession $session, User $user, int $questionNumber): void
    {
        if (! $session->isParticipant($user->id)) {
            return;
        }

        $questionNumber = max(0, min($questionNumber, DuelSession::TOTAL_QUESTIONS));

        if ($user->id === $session->host_user_id) {
            if ($questionNumber > $session->host_progress) {
                DuelSession::query()->whereKey($session->id)->update(['host_progress' => $questionNumber]);
            }
        } elseif ($questionNumber > $session->opponent_progress) {
            DuelSession::query()->whereKey($session->id)->update(['opponent_progress' => $questionNumber]);
        }
    }

    public function submitPlayerAttempt(DuelSession $session, User $user, ExamAttempt $attempt): DuelSession
    {
        return DB::transaction(function () use ($session, $user, $attempt) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($attempt->user_id !== $user->id || $attempt->duel_session_id !== $session->id) {
                throw new AccessDeniedHttpException('Attempt tidak valid untuk duel ini.');
            }

            $attempt = $this->finalizeAttempt($attempt);

            $finishedAt = now();

            if ($user->id === $session->host_user_id) {
                $session->update(['host_finished_at' => $finishedAt]);
            } else {
                $session->update(['opponent_finished_at' => $finishedAt]);
            }

            $session = $session->fresh(['hostAttempt', 'opponentAttempt']);

            if ($session->is_bot_opponent && ! $session->opponent_finished_at) {
                $this->shadowBot->completeBotAttempt($session);
                $session = $session->fresh(['hostAttempt', 'opponentAttempt']);
            }

            return $this->resolveSessionIfComplete($session);
        });
    }

    public function checkExpiry(DuelSession $session): DuelSession
    {
        return DB::transaction(function () use ($session) {
            $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->status !== DuelSessionStatus::InProgress || $session->expires_at === null) {
                return $session;
            }

            if (now()->lt($session->expires_at)) {
                return $session;
            }

            if ($session->host_attempt_id && ! $session->host_finished_at) {
                $this->finalizeAttempt($session->hostAttempt);
                $session->update(['host_finished_at' => now()]);
            }

            if ($session->opponent_attempt_id && ! $session->opponent_finished_at && ! $session->is_bot_opponent) {
                $this->finalizeAttempt($session->opponentAttempt);
                $session->update(['opponent_finished_at' => now()]);
            }

            if ($session->is_bot_opponent && ! $session->opponent_finished_at) {
                $this->shadowBot->completeBotAttempt($session->fresh());
                $session = $session->fresh(['hostAttempt', 'opponentAttempt']);
            }

            $session = $session->fresh();

            if (! $session->host_finished_at && ! $session->host_attempt_id) {
                $session->update(['host_finished_at' => $session->expires_at]);
            }

            if (! $session->is_bot_opponent && ! $session->opponent_finished_at && ! $session->opponent_attempt_id) {
                $session->update(['opponent_finished_at' => $session->expires_at]);
            }

            return $this->resolveSessionIfComplete($session->fresh(['hostAttempt', 'opponentAttempt']));
        });
    }

    public function tickBotProgress(DuelSession $session): void
    {
        if ($session->is_bot_opponent && $session->status === DuelSessionStatus::InProgress) {
            $this->shadowBot->advanceProgress($session);
        }
    }

    private function createSession(User $host, DuelMatchType $matchType, ?int $opponentId = null): DuelSession
    {
        $this->questionGenerator->assertSufficientQuestions();

        return DuelSession::query()->create([
            'code' => $this->generateUniqueCode(),
            'host_user_id' => $host->id,
            'opponent_user_id' => $opponentId,
            'question_ids' => $this->questionGenerator->generate(),
            'status' => DuelSessionStatus::Waiting,
            'match_type' => $matchType,
            'duration_minutes' => DuelSession::DURATION_MINUTES,
        ]);
    }

    private function joinSession(DuelSession $session, User $opponent): DuelSession
    {
        $session = DuelSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

        if ($session->status !== DuelSessionStatus::Waiting) {
            throw ValidationException::withMessages([
                'duel' => 'Duel ini sudah tidak menerima peserta.',
            ]);
        }

        return $this->assignOpponentAndStart($session, $opponent);
    }

    private function assignOpponentAndStart(DuelSession $session, User $opponent): DuelSession
    {
        $session->update([
            'opponent_user_id' => $opponent->id,
            'is_bot_opponent' => false,
            'status' => DuelSessionStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($session->duration_minutes),
        ]);

        return $session->fresh();
    }

    private function startPendingDuel(DuelSession $session): DuelSession
    {
        $session->update([
            'status' => DuelSessionStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($session->duration_minutes),
        ]);

        return $session->fresh();
    }

    private function assignBotAndStart(DuelSession $session): DuelSession
    {
        $bot = $this->shadowBot->botUser();

        $session->update([
            'opponent_user_id' => $bot->id,
            'is_bot_opponent' => true,
            'status' => DuelSessionStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($session->duration_minutes),
        ]);

        return $session->fresh();
    }

    private function createAttemptFromSession(DuelSession $session, User $user): ExamAttempt
    {
        $exam = $this->duelExam();
        $items = $this->questionGenerator->toQuestionItems($session->question_ids);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'duel_session_id' => $session->id,
            'user_id' => $user->id,
            'started_at' => now(),
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

    private function finalizeAttempt(ExamAttempt $attempt): ExamAttempt
    {
        $attempt = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

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

        return $attempt->fresh();
    }

    private function resolveSessionIfComplete(DuelSession $session): DuelSession
    {
        if ($session->status === DuelSessionStatus::Completed) {
            return $session;
        }

        $hostDone = $session->host_finished_at !== null;
        $opponentDone = $session->is_bot_opponent
            ? $session->opponent_finished_at !== null
            : $session->opponent_finished_at !== null;

        if (! $hostDone || ! $opponentDone) {
            return $session;
        }

        $hostScore = (int) ($session->hostAttempt?->total_score ?? 0);
        $opponentScore = (int) ($session->opponentAttempt?->total_score ?? 0);

        $winnerId = null;

        if ($hostScore > $opponentScore) {
            $winnerId = $session->host_user_id;
        } elseif ($opponentScore > $hostScore) {
            $winnerId = $session->opponent_user_id;
        } elseif ($session->host_finished_at->lt($session->opponent_finished_at)) {
            $winnerId = $session->host_user_id;
        } elseif ($session->opponent_finished_at->lt($session->host_finished_at)) {
            $winnerId = $session->opponent_user_id;
        }

        $session->update([
            'status' => DuelSessionStatus::Completed,
            'winner_user_id' => $winnerId,
        ]);

        $session = $session->fresh(['host', 'opponent', 'winner', 'hostAttempt', 'opponentAttempt']);

        $this->awardDuelXp($session);

        return $session;
    }

    private function awardDuelXp(DuelSession $session): void
    {
        $planner = app(LearningPlanService::class);

        if ($session->hostAttempt && $session->host) {
            $this->gamificationService->awardDuelAttemptXp(
                $session->hostAttempt,
                $session->host,
                $session->winner_user_id === $session->host_user_id,
            );
            $planner->completeMatchingTasks($session->host, LearningPlanTaskCategory::Duel);
        }

        if ($session->opponentAttempt && $session->opponent && ! $session->is_bot_opponent) {
            $this->gamificationService->awardDuelAttemptXp(
                $session->opponentAttempt,
                $session->opponent,
                $session->winner_user_id === $session->opponent_user_id,
            );
            $planner->completeMatchingTasks($session->opponent, LearningPlanTaskCategory::Duel);
        }
    }

    private function duelExam(): Exam
    {
        return Exam::query()->firstOrCreate(
            ['slug' => 'duel-mini-tryout'],
            [
                'title' => 'Duel Mini-Tryout',
                'description' => 'Duel 1v1 — 15 soal TWK, TIU, dan TKP dalam 10 menit.',
                'duration_minutes' => DuelSession::DURATION_MINUTES,
                'status' => ExamStatus::Published,
                'settings' => ['difficulty' => 'all', 'is_duel' => true],
            ],
        );
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (DuelSession::query()->where('code', $code)->exists());

        return $code;
    }
}
