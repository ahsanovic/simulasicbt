<?php

namespace App\Livewire\Peserta;

use App\Services\GhostRaceService;
use Livewire\Component;

class GhostRaceTrack extends Component
{
    public function mount(GhostRaceService $ghostRaceService): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $message = $ghostRaceService->evaluateRivalGapNotification($user);

        if ($message !== null) {
            $this->dispatch('ghost-rival-pulled-ahead', message: $message);
        }
    }

    public function selectRival(string $rivalUserId, GhostRaceService $ghostRaceService): void
    {
        $rivalId = $rivalUserId !== '' ? (int) $rivalUserId : null;
        $ghostRaceService->selectRival(auth()->user(), $rivalId);
    }

    public function toggleNotifications(GhostRaceService $ghostRaceService): void
    {
        $user = auth()->user();
        $ghostRaceService->setNotificationsMuted($user, ! $user->ghost_race_notifications_muted);
    }

    public function render(GhostRaceService $ghostRaceService)
    {
        return view('livewire.peserta.ghost-race-track', [
            'state' => $ghostRaceService->getTrackState(auth()->user()),
        ]);
    }
}
