<?php

namespace App\Services\ModeUjianRoom;

use App\Enums\ExamAttemptStatus;
use App\Support\ExamDeadline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * "Selesai Ujian" and the browser's time-up call: the answers the browser has
 * not sent yet are saved and the attempt is scored in one transaction, so it
 * can never be scored before its last answers are in.
 */
final class ClientRoomFinisher
{
    public function __construct(private readonly AnswerBatchWriter $writer) {}

    /**
     * @param  list<array{id: int, option_id: ?int, is_marked: bool, version: int}>  $answers
     * @return array{status: 'submitted'|'extended'|'device', attempt: Model}
     */
    public function finish(RoomKind $kind, Model $attempt, string $deviceToken, array $answers, bool $timeUp): array
    {
        return DB::transaction(function () use ($kind, $attempt, $deviceToken, $answers, $timeUp) {
            $attempt = $kind->lockAttempt((int) $attempt->getKey());

            if ($attempt->status !== ExamAttemptStatus::InProgress) {
                return ['status' => 'submitted', 'attempt' => $attempt];
            }

            if (! DeviceToken::matches($attempt, $deviceToken)) {
                return ['status' => 'device', 'attempt' => $attempt];
            }

            if (ExamDeadline::acceptsAnswers($attempt->expires_at)) {
                try {
                    $this->writer->write($kind, $attempt, $deviceToken, $answers);
                } catch (ValidationException $exception) {
                    // A malformed leftover must not block the submit itself.
                    Log::warning('Mode Ujian finish: leftover answers ignored', [
                        'kind' => $kind->key(),
                        'attempt_id' => $attempt->getKey(),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            // The browser timer hit zero but the admin added time meanwhile.
            if ($timeUp && now()->lt($attempt->expires_at)) {
                return ['status' => 'extended', 'attempt' => $attempt];
            }

            return ['status' => 'submitted', 'attempt' => $kind->submit($attempt)];
        });
    }
}
