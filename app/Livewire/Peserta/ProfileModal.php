<?php

namespace App\Livewire\Peserta;

use App\Enums\DevotionBadge;
use App\Rules\ValidDisplayName;
use App\Services\CoinService;
use App\Services\GamificationService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ProfileModal extends Component
{
    private const NAME_UPDATE_RATE_LIMIT = 5;

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
        $this->ensureWithinRateLimit();

        $this->name = sanitize_display_name($this->name);

        $user = auth()->user()?->fresh();

        if (! $user?->usesGoogleAuth()) {
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', new ValidDisplayName],
        ], [], [
            'name' => 'Nama',
        ]);

        $trimmedName = sanitize_display_name($validated['name']);

        if ($trimmedName === $user->name) {
            return;
        }

        $user->update(['name' => $trimmedName]);

        $this->name = $trimmedName;

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

    private function ensureWithinRateLimit(): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $key = 'profile-name-update:'.$userId;

        if (RateLimiter::tooManyAttempts($key, self::NAME_UPDATE_RATE_LIMIT)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'name' => 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
