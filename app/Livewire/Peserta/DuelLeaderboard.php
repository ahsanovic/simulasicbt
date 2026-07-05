<?php

namespace App\Livewire\Peserta;

use App\Services\DuelLeaderboardService;
use Livewire\Component;

class DuelLeaderboard extends Component
{
    public function render(DuelLeaderboardService $leaderboard)
    {
        $data = $leaderboard->getLeaderboard((int) auth()->id());

        return view('livewire.peserta.duel-leaderboard', [
            'entries' => $data['entries'],
            'currentUser' => $data['current_user'],
        ]);
    }
}
