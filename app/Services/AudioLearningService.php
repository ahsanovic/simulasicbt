<?php

namespace App\Services;

use App\Enums\SubjectCode;
use App\Models\AudioLearningSession;
use App\Models\User;
use Carbon\Carbon;

class AudioLearningService
{
    public function recordSession(
        User $user,
        SubjectCode $subjectCode,
        int $questionCount,
        int $durationSeconds,
    ): AudioLearningSession {
        return AudioLearningSession::query()->create([
            'user_id' => $user->id,
            'subject_code' => $subjectCode,
            'question_count' => $questionCount,
            'xp_earned' => $questionCount,
            'duration_seconds' => max(0, $durationSeconds),
            'completed_at' => now(),
        ]);
    }

    public function dailyStreak(User $user): int
    {
        $dates = AudioLearningSession::query()
            ->where('user_id', $user->id)
            ->selectRaw('DATE(completed_at) as session_date')
            ->distinct()
            ->orderByDesc('session_date')
            ->pluck('session_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $firstDate = $dates->first();

        if (! $firstDate->equalTo($today) && ! $firstDate->equalTo($yesterday)) {
            return 0;
        }

        $streak = 1;
        $expected = $firstDate->copy()->subDay();

        foreach ($dates->skip(1) as $date) {
            if (! $date->equalTo($expected)) {
                break;
            }

            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    public function totalXp(User $user): int
    {
        return (int) AudioLearningSession::query()
            ->where('user_id', $user->id)
            ->sum('xp_earned');
    }
}
