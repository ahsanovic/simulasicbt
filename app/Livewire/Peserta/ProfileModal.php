<?php

namespace App\Livewire\Peserta;

use App\Enums\DevotionBadge;
use App\Services\CoinService;
use App\Services\GamificationService;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileModal extends Component
{
    public bool $showModal = false;

    public string $name = '';

    #[On('open-profile-modal')]
    public function openModal(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->name = $user->name;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function updateName(): void
    {
        $user = auth()->user();

        if (! $user?->usesGoogleAuth()) {
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $trimmedName = trim($validated['name']);

        $user->update(['name' => $trimmedName]);

        $this->dispatch('profile-name-updated', name: $trimmedName);

        session()->flash('profile-success', 'Nama berhasil diperbarui.');
    }

    public function render(CoinService $coinService, GamificationService $gamificationService)
    {
        $user = auth()->user();

        if (! $user) {
            return view('livewire.peserta.profile-modal', [
                'user' => null,
                'coinBalance' => 0,
                'totalXp' => 0,
                'currentBadge' => null,
                'canEditName' => false,
            ]);
        }

        $totalXp = $gamificationService->totalXp($user);

        return view('livewire.peserta.profile-modal', [
            'user' => $user,
            'coinBalance' => $coinService->balance($user),
            'totalXp' => $totalXp,
            'currentBadge' => DevotionBadge::fromXp($totalXp)->toArray(),
            'canEditName' => $user->usesGoogleAuth(),
        ]);
    }
}
