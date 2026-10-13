<?php

namespace App\Services\ModeUjianRoom;

use App\Enums\ExamAttemptStatus;
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Services\SkbExamService;
use App\Support\ExamQuestionCache;
use Illuminate\Database\Eloquent\Model;

final class SkbRoom implements RoomKind
{
    public function key(): string
    {
        return 'skb';
    }

    public function activeAttempt(int $userId): ?SkbExamAttempt
    {
        return SkbExamAttempt::query()
            ->where('user_id', $userId)
            ->where('status', ExamAttemptStatus::InProgress)
            ->latest('id')
            ->first();
    }

    public function ownedAttempt(int $userId, int $attemptId): ?SkbExamAttempt
    {
        return SkbExamAttempt::query()->where('user_id', $userId)->find($attemptId);
    }

    public function lockAttempt(int $attemptId): SkbExamAttempt
    {
        return SkbExamAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }

    public function answerModel(): string
    {
        return SkbExamAnswer::class;
    }

    public function answerAttemptColumn(): string
    {
        return 'skb_exam_attempt_id';
    }

    public function answerQuestionColumn(): string
    {
        return 'skb_question_id';
    }

    public function answerValues(?int $optionId): array
    {
        return ['selected_option_id' => $optionId];
    }

    public function questions(array $questionIds): array
    {
        return ExamQuestionCache::skbMany($questionIds);
    }

    public function badge(Model $question): ?string
    {
        return null;
    }

    public function title(Model $attempt): string
    {
        return 'Ujian SKB';
    }

    public function submit(Model $attempt): SkbExamAttempt
    {
        return app(SkbExamService::class)->submitAttempt($attempt);
    }

    public function closeExpired(Model $attempt): SkbExamAttempt
    {
        return app(SkbExamService::class)->submitAttempt($attempt);
    }

    public function resultUrl(Model $attempt): string
    {
        return route('peserta.mode-ujian.skb-result', $attempt);
    }

    public function legacyRoomUrl(Model $attempt): string
    {
        return route('peserta.mode-ujian.skb.room');
    }
}
