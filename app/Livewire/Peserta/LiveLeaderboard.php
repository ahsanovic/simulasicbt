<?php

namespace App\Livewire\Peserta;

use App\Services\LeaderboardService;
use Livewire\Component;

class LiveLeaderboard extends Component
{
    public function render(LeaderboardService $leaderboard)
    {
        $data = $leaderboard->getLiveLeaderboard((int) auth()->id());

        return view('livewire.peserta.live-leaderboard', [
            'entries' => $data['entries'],
            'currentUser' => $data['current_user'],
        ]);
    }
}
