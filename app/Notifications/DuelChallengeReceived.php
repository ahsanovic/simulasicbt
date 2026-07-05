<?php

namespace App\Notifications;

use App\Models\DuelSession;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DuelChallengeReceived extends Notification
{
    use Queueable;

    public function __construct(
        public readonly DuelSession $session,
        public readonly User $challenger,
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
            'duel_session_id' => $this->session->id,
            'challenger_id' => $this->challenger->id,
            'challenger_name' => $this->challenger->name,
            'message' => "{$this->challenger->name} menantang Anda dalam duel 1v1!",
            'url' => route('peserta.duel.room', $this->session),
        ];
    }
}
