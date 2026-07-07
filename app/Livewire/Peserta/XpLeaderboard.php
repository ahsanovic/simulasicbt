<?php

namespace App\Livewire\Peserta;

use App\Services\XpLeaderboardService;
use Livewire\Component;

class XpLeaderboard extends Component
{
    public function render(XpLeaderboardService $leaderboard)
    {
        $data = $leaderboard->getLeaderboard((int) auth()->id());

        return view('livewire.peserta.xp-leaderboard', [
            'entries' => $data['entries'],
            'currentUser' => $data['current_user'],
        ]);
    }
}
