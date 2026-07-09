<?php

namespace App\Services;

use App\Enums\DailyActivityType;
use App\Enums\SubjectCode;
use App\Models\AudioLearningSession;
use App\Models\User;

class AudioLearningService
{
    public function recordSession(
        User $user,
        SubjectCode $subjectCode,
        int $questionCount,
        int $durationSeconds,
    ): AudioLearningSession {
        $dailyStreak = app(DailyStreakService::class);
        $dailyStreak->logActivity($user, DailyActivityType::Audio);
        $xpEarned = $dailyStreak->applyMultiplier($questionCount, $dailyStreak->dailyStreak($user));

        return AudioLearningSession::query()->create([
            'user_id' => $user->id,
            'subject_code' => $subjectCode,
            'question_count' => $questionCount,
            'xp_earned' => $xpEarned,
            'duration_seconds' => max(0, $durationSeconds),
            'completed_at' => now(),
        ]);
    }

    public function totalXp(User $user): int
    {
        return app(GamificationService::class)->totalXp($user);
    }
}
