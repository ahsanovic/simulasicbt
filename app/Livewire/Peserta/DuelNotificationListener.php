<?php

namespace App\Livewire\Peserta;

use App\Notifications\DuelChallengeReceived;
use Livewire\Component;

class DuelNotificationListener extends Component
{
    public function pollChallengeNotifications(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $notifications = $user->unreadNotifications()
            ->where('type', DuelChallengeReceived::class)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($notifications as $notification) {
            /** @var array{message: string, url: string} $data */
            $data = $notification->data;
            $notification->markAsRead();

            $this->dispatch(
                'duel-challenge-received',
                message: $data['message'],
                url: $data['url'],
            );
        }
    }

    public function render()
    {
        return view('livewire.peserta.duel-notification-listener');
    }
}
