<?php

namespace App\Services\ModeUjianRoom;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use App\Support\ExamQuestionCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SkdRoom implements RoomKind
{
    public function key(): string
    {
        return 'skd';
    }

    public function activeAttempt(int $userId): ?ExamAttempt
    {
        return $this->modeUjianAttempts($userId)
            ->where('status', ExamAttemptStatus::InProgress)
            ->latest('id')
            ->first();
    }

    public function ownedAttempt(int $userId, int $attemptId): ?ExamAttempt
    {
        return $this->modeUjianAttempts($userId)->find($attemptId);
    }

    public function lockAttempt(int $attemptId): ExamAttempt
    {
        return ExamAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }

    public function answerModel(): string
    {
        return ExamAnswer::class;
    }

    public function answerAttemptColumn(): string
    {
        return 'exam_attempt_id';
    }

    public function answerQuestionColumn(): string
    {
        return 'question_id';
    }

    public function answerValues(?int $optionId): array
    {
        return ['selected_option_id' => $optionId, 'answered_at' => $optionId !== null ? now() : null];
    }

    public function questions(array $questionIds): array
    {
        return ExamQuestionCache::skdMany($questionIds);
    }

    public function badge(Model $question): ?string
    {
        return $question->subject?->code?->label();
    }

    public function title(Model $attempt): string
    {
        return $attempt->exam?->title ?? 'Ujian SKD';
    }

    public function submit(Model $attempt): ExamAttempt
    {
        return app(ExamService::class)->submitAttempt($attempt);
    }

    public function closeExpired(Model $attempt): ExamAttempt
    {
        app(ExamService::class)->finalizeExpiredAttempts([$attempt]);

        return $attempt->fresh();
    }

    public function resultUrl(Model $attempt): string
    {
        return route('peserta.mode-ujian.skd-result', $attempt);
    }

    public function legacyRoomUrl(Model $attempt): string
    {
        return route('peserta.exam.room', $attempt->exam_id);
    }

    private function modeUjianAttempts(int $userId): Builder
    {
        return ExamAttempt::query()
            ->where('user_id', $userId)
            ->whereHas('event', fn (Builder $query) => $query->where('is_mode_ujian', true));
    }
}
