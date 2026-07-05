<?php

namespace App\Notifications;

use App\Models\DuelSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DuelChallengeRejected extends Notification
{
    use Queueable;

    public function __construct(
        public readonly DuelSession $session,
        public readonly User $opponent,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'challenge_rejected',
            'duel_session_id' => $this->session->id,
            'opponent_name' => $this->opponent->name,
            'message' => "{$this->opponent->name} menolak tantangan duel Anda.",
        ];
    }
}
