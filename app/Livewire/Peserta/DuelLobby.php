<?php

namespace App\Livewire\Peserta;

use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use App\Models\DuelSession;
use App\Notifications\DuelChallengeReceived;
use App\Services\DuelService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'duel', 'showNav' => true])]
#[Title('Challenge a Friend')]
class DuelLobby extends Component
{
    public string $mode = 'menu';

    public string $friendIdentifier = '';

    public string $joinCode = '';

    public ?DuelSession $waitingSession = null;

    public function mount(): void
    {
        $activeDuel = DuelSession::query()
            ->where(function ($query) {
                $query->where('host_user_id', auth()->id())
                    ->orWhere('opponent_user_id', auth()->id());
            })
            ->where('status', DuelSessionStatus::InProgress)
            ->latest()
            ->first();

        if ($activeDuel) {
            $this->redirect(route('peserta.duel.room', $activeDuel), navigate: true);

            return;
        }

        $pendingChallenge = DuelSession::query()
            ->where('host_user_id', auth()->id())
            ->where('status', DuelSessionStatus::Waiting)
            ->where('match_type', DuelMatchType::Friend)
            ->latest()
            ->first();

        if ($pendingChallenge) {
            $this->waitingSession = $pendingChallenge->load('opponent');
            $this->mode = 'challenge_pending';

            return;
        }

        $queuedMatchmaking = DuelSession::query()
            ->where('host_user_id', auth()->id())
            ->where('status', DuelSessionStatus::Waiting)
            ->where('match_type', DuelMatchType::Random)
            ->latest()
            ->first();

        if ($queuedMatchmaking) {
            $this->waitingSession = $queuedMatchmaking;
            $this->mode = 'matchmaking';
        }
    }

    public function findRandomMatch(DuelService $duelService): void
    {
        $session = $duelService->enterMatchmakingQueue(auth()->user());

        if ($session->status === DuelSessionStatus::InProgress) {
            $this->redirect(route('peserta.duel.room', $session), navigate: true);

            return;
        }

        $this->waitingSession = $session;
        $this->mode = 'matchmaking';
    }

    public function checkMatchmaking(DuelService $duelService): void
    {
        if (! $this->waitingSession || $this->mode !== 'matchmaking') {
            return;
        }

        $session = $duelService->pollMatchmaking($this->waitingSession, auth()->user());

        if ($session->status === DuelSessionStatus::InProgress) {
            $this->redirect(route('peserta.duel.room', $session), navigate: true);
        } else {
            $this->waitingSession = $session;
        }
    }

    public function cancelMatchmaking(DuelService $duelService): void
    {
        $duelService->cancelMatchmaking(auth()->user());
        $this->waitingSession = null;
        $this->mode = 'menu';
    }

    public function challengeFriend(DuelService $duelService): void
    {
        $this->validate([
            'friendIdentifier' => ['required', 'string', 'min:3'],
        ], [
            'friendIdentifier.required' => 'Masukkan username, NIP, atau email teman.',
        ]);

        $result = $duelService->challengeFriend(auth()->user(), trim($this->friendIdentifier));

        $this->waitingSession = $result->session->load('opponent');
        $this->mode = 'challenge_pending';

        if ($result->opponentWasOnline) {
            session()->flash('info', "Menunggu {$result->opponent->name} menerima tantangan duel...");
        } else {
            session()->flash('warning', "{$result->opponent->name} sedang tidak online. Tantangan akan diterima saat lawan membuka aplikasi.");
        }
    }

    public function checkChallengeResponse(): void
    {
        if (! $this->waitingSession || $this->mode !== 'challenge_pending') {
            return;
        }

        $session = DuelSession::query()->find($this->waitingSession->id);

        if (! $session) {
            $this->waitingSession = null;
            $this->mode = 'menu';

            return;
        }

        if ($session->status === DuelSessionStatus::InProgress) {
            session()->flash('success', 'Lawan menerima tantangan! Duel dimulai.');
            $this->redirect(route('peserta.duel.room', $session), navigate: true);

            return;
        }

        if ($session->status === DuelSessionStatus::Cancelled) {
            $this->waitingSession = null;
            $this->mode = 'menu';
            session()->flash('warning', 'Lawan menolak tantangan duel.');
        }
    }

    public function cancelChallengePending(DuelService $duelService): void
    {
        if ($this->waitingSession && $this->mode === 'challenge_pending') {
            $duelService->cancelFriendChallenge($this->waitingSession, auth()->user());
        }

        $this->waitingSession = null;
        $this->mode = 'menu';
    }

    public function createInviteCode(DuelService $duelService): void
    {
        $this->waitingSession = $duelService->createInviteCode(auth()->user());
        $this->mode = 'waiting';
    }

    public function joinByCode(DuelService $duelService): void
    {
        $this->validate([
            'joinCode' => ['required', 'string', 'min:4'],
        ], [
            'joinCode.required' => 'Masukkan kode duel.',
        ]);

        $session = $duelService->joinByCode(auth()->user(), $this->joinCode);
        $this->redirect(route('peserta.duel.room', $session), navigate: true);
    }

    public function checkWaitingRoom(): void
    {
        if (! $this->waitingSession || $this->mode !== 'waiting') {
            return;
        }

        $session = DuelSession::query()->find($this->waitingSession->id);

        if ($session && $session->status === DuelSessionStatus::InProgress) {
            $this->redirect(route('peserta.duel.room', $session), navigate: true);
        }
    }

    public function cancelWaiting(DuelService $duelService): void
    {
        if ($this->waitingSession && $this->mode === 'waiting') {
            $duelService->cancelInviteCode($this->waitingSession, auth()->user());
        }

        $this->waitingSession = null;
        $this->mode = 'menu';
    }

    public function render()
    {
        $recentDuels = DuelSession::query()
            ->where(function ($query) {
                $query->where('host_user_id', auth()->id())
                    ->orWhere('opponent_user_id', auth()->id());
            })
            ->where('status', DuelSessionStatus::Completed)
            ->with(['host', 'opponent', 'winner', 'hostAttempt', 'opponentAttempt'])
            ->latest()
            ->limit(5)
            ->get();

        $enableNotificationPoll = $this->shouldPollNotifications();

        return view('livewire.peserta.duel-lobby', compact('recentDuels', 'enableNotificationPoll'));
    }

    private function shouldPollNotifications(): bool
    {
        if (in_array($this->mode, ['challenge_pending', 'matchmaking', 'waiting'], true)) {
            return true;
        }

        return auth()->user()->unreadNotifications()
            ->where('type', DuelChallengeReceived::class)
            ->exists();
    }
}
