<?php

namespace App\Livewire\Peserta;

use App\Models\DuelSession;
use App\Notifications\DuelChallengeAccepted;
use App\Notifications\DuelChallengeReceived;
use App\Notifications\DuelChallengeRejected;
use App\Services\DuelService;
use Livewire\Attributes\On;
use Livewire\Component;

class DuelNotificationListener extends Component
{
    /** @var list<string> */
    private static array $dispatchedNotificationIds = [];

    public function pollChallengeNotifications(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->pollIncomingChallenges($user);
        $this->pollChallengeResponses($user);
    }

    #[On('accept-duel-challenge')]
    public function acceptChallenge(int $sessionId, DuelService $duelService): void
    {
        $session = DuelSession::query()->findOrFail($sessionId);
        $session = $duelService->acceptFriendChallenge($session, auth()->user());

        $this->clearChallengeNotifications($sessionId);

        $this->redirect(route('peserta.duel.room', $session), navigate: true);
    }

    #[On('reject-duel-challenge')]
    public function rejectChallenge(int $sessionId, string $notificationId, DuelService $duelService): void
    {
        $session = DuelSession::query()->findOrFail($sessionId);
        $duelService->rejectFriendChallenge($session, auth()->user());

        auth()->user()->notifications()->whereKey($notificationId)->delete();

        $this->dispatch('duel-challenge-rejected-self', message: 'Anda menolak tantangan duel.');
    }

    #[On('mark-challenge-notification-read')]
    public function markChallengeHandled(string $notificationId): void
    {
        auth()->user()?->notifications()->whereKey($notificationId)->delete();
    }

    private function clearChallengeNotifications(int $sessionId): void
    {
        auth()->user()?->notifications()
            ->where('type', DuelChallengeReceived::class)
            ->where('data->duel_session_id', $sessionId)
            ->delete();
    }

    private function pollIncomingChallenges($user): void
    {
        $notifications = $user->unreadNotifications()
            ->where('type', DuelChallengeReceived::class)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($notifications as $notification) {
            if (in_array($notification->id, self::$dispatchedNotificationIds, true)) {
                continue;
            }

            /** @var array{message: string, duel_session_id: int} $data */
            $data = $notification->data;

            self::$dispatchedNotificationIds[] = $notification->id;

            $this->dispatch(
                'duel-challenge-received',
                message: $data['message'],
                sessionId: (int) $data['duel_session_id'],
                notificationId: $notification->id,
            );
        }
    }

    private function pollChallengeResponses($user): void
    {
        $accepted = $user->unreadNotifications()
            ->where('type', DuelChallengeAccepted::class)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($accepted as $notification) {
            if (in_array($notification->id, self::$dispatchedNotificationIds, true)) {
                continue;
            }

            /** @var array{message: string, url: string, duel_session_id: int} $data */
            $data = $notification->data;
            $notification->markAsRead();
            self::$dispatchedNotificationIds[] = $notification->id;

            $this->redirect($data['url'], navigate: true);
        }

        $rejected = $user->unreadNotifications()
            ->where('type', DuelChallengeRejected::class)
            ->orderBy('created_at')
            ->limit(3)
            ->get();

        foreach ($rejected as $notification) {
            if (in_array($notification->id, self::$dispatchedNotificationIds, true)) {
                continue;
            }

            /** @var array{message: string} $data */
            $data = $notification->data;
            $notification->markAsRead();
            self::$dispatchedNotificationIds[] = $notification->id;

            $this->dispatch('duel-challenge-rejected', message: $data['message']);
        }
    }

    public function render()
    {
        return view('livewire.peserta.duel-notification-listener');
    }
}
