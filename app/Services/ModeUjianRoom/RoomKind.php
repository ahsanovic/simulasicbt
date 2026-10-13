<?php

namespace App\Services\ModeUjianRoom;

use Illuminate\Database\Eloquent\Model;

/**
 * What differs between the SKD and SKB Mode Ujian rooms (attempt and answer
 * tables, question cache, scoring, result page). Everything else in the
 * browser room is shared.
 */
interface RoomKind
{
    /** 'skd' or 'skb'. */
    public function key(): string;

    /** The user's in-progress Mode Ujian attempt, or null. */
    public function activeAttempt(int $userId): ?Model;

    /** The user's Mode Ujian attempt by id (any status), or null. */
    public function ownedAttempt(int $userId, int $attemptId): ?Model;

    /**
     * The user's attempt row, locked for update (call inside a transaction).
     * Only the attempt row is locked: whether it is a Mode Ujian attempt is
     * settled by the device token, which only the Mode Ujian room hands out.
     */
    public function lockOwnedAttempt(int $userId, int $attemptId): ?Model;

    /** @return class-string<Model> */
    public function answerModel(): string;

    public function answerAttemptColumn(): string;

    public function answerQuestionColumn(): string;

    /** Columns written when an option is saved (null = answer cleared). */
    public function answerValues(?int $optionId): array;

    /**
     * Display copies of the questions with their options, from the question cache.
     *
     * @param  list<int>  $questionIds
     * @return array<int, Model> keyed by question id; missing questions left out
     */
    public function questions(array $questionIds): array;

    /** Short label shown above the question (e.g. "TWK"), or null. */
    public function badge(Model $question): ?string;

    public function title(Model $attempt): string;

    /** Score and close the attempt (the existing service; scoring unchanged). */
    public function submit(Model $attempt): Model;

    /** Close an attempt whose time already ran out, with its saved answers. */
    public function closeExpired(Model $attempt): Model;

    public function resultUrl(Model $attempt): string;

    /** The Livewire room used while the browser room is switched off. */
    public function legacyRoomUrl(Model $attempt): string;
}
