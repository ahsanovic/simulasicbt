<?php

namespace App\Services;

use App\Models\DuelSession;
use App\Models\User;

final class DuelChallengeResult
{
    public function __construct(
        public readonly DuelSession $session,
        public readonly User $opponent,
        public readonly bool $opponentWasOnline,
    ) {}
}
