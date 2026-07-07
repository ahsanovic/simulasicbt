<?php

namespace App\Livewire\Peserta;

use App\Services\DuelLeaderboardService;
use App\Services\LeaderboardService;
use App\Services\XpLeaderboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'dashboard', 'showNav' => true])]
#[Title('Papan Peringkat')]
class LeaderboardHub extends Component
{
    private const int HUB_LIMIT = 50;

    #[Url(as: 'tab', history: true)]
    public string $tab = 'score';

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['score', 'duel', 'xp'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function render(
        LeaderboardService $scoreLeaderboard,
        DuelLeaderboardService $duelLeaderboard,
        XpLeaderboardService $xpLeaderboard,
    ) {
        $userId = (int) auth()->id();

        return view('livewire.peserta.leaderboard-hub', match ($this->tab) {
            'duel' => [
                'duelData' => $duelLeaderboard->getLeaderboard($userId, self::HUB_LIMIT),
            ],
            'xp' => [
                'xpData' => $xpLeaderboard->getLeaderboard($userId, self::HUB_LIMIT),
            ],
            default => [
                'scoreData' => $scoreLeaderboard->getLiveLeaderboard($userId, self::HUB_LIMIT),
            ],
        });
    }
}
