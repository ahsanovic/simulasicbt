<?php

namespace App\Services;

use App\Models\CoinTransaction;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoinService
{
    public const EXAM_PASS_COIN_REWARD = 50;

    public const EXAM_FAIL_COIN_REWARD = 15;

    public function balance(User $user): int
    {
        return (int) CoinTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount');
    }

    public function award(User $user, string $sourceType, int $sourceId, int $amount, string $reason): ?CoinTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return CoinTransaction::query()->firstOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
            ],
        );
    }

    public function spend(User $user, int $amount, string $reason, string $sourceType, int $sourceId): CoinTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'coins' => 'Jumlah koin tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($user, $amount, $reason, $sourceType, $sourceId) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->balance($user) < $amount) {
                throw ValidationException::withMessages([
                    'coins' => 'Saldo koin tidak cukup.',
                ]);
            }

            return CoinTransaction::query()->create([
                'user_id' => $user->id,
                'amount' => -$amount,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);
        });
    }

    public function awardExamAttemptCoins(ExamAttempt $attempt, User $user): ?CoinTransaction
    {
        if ($attempt->duel_session_id !== null || $attempt->isRemedial()) {
            return null;
        }

        $passed = exam_attempt_passes(
            $attempt->score_twk,
            $attempt->score_tiu,
            $attempt->score_tkp,
            $attempt->total_score,
        );

        $amount = $passed ? self::EXAM_PASS_COIN_REWARD : self::EXAM_FAIL_COIN_REWARD;
        $reason = $passed ? 'Reward lulus simulasi' : 'Reward ikut simulasi';

        return $this->award($user, ExamAttempt::class, $attempt->id, $amount, $reason);
    }
}
