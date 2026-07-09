<?php

namespace App\Livewire\Peserta;

use App\Enums\FlashcardRating;
use App\Models\Flashcard;
use App\Services\FlashcardService;
use App\Services\GamificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kartu Sakti')]
class KartuSakti extends Component
{
    public string $mode = 'setup';

    /** @var list<int> */
    #[Locked]
    public array $cardIds = [];

    public int $currentIndex = 0;

    public bool $revealed = false;

    public int $reviewedCount = 0;

    public int $sessionStartedAt = 0;

    public ?int $summaryDurationSeconds = null;

    public ?int $summaryXp = null;

    public ?int $summaryBaseXp = null;

    public int $dailyStreak = 0;

    public string $streakMultiplierLabel = '1x';

    public int $totalXp = 0;

    public int $dueCount = 0;

    public int $activeCount = 0;

    public function mount(FlashcardService $flashcardService, GamificationService $gamificationService): void
    {
        $user = auth()->user();
        $this->dueCount = $flashcardService->dueCount($user);
        $this->activeCount = $flashcardService->activeCount($user);
        $streakInfo = $gamificationService->dailyStreakInfo($user);
        $this->dailyStreak = $streakInfo['streak'];
        $this->streakMultiplierLabel = $streakInfo['multiplier_label'];
        $this->totalXp = $gamificationService->totalXp($user);
    }

    public function startReview(FlashcardService $flashcardService): void
    {
        $cards = $flashcardService->dueCards(auth()->user());

        if ($cards->isEmpty()) {
            throw ValidationException::withMessages([
                'review' => 'Tidak ada kartu yang perlu direview hari ini. Kembali besok atau simpan kartu baru dari pembahasan soal.',
            ]);
        }

        $this->cardIds = $cards->pluck('id')->all();
        $this->currentIndex = 0;
        $this->revealed = false;
        $this->reviewedCount = 0;
        $this->sessionStartedAt = now()->timestamp;
        $this->summaryDurationSeconds = null;
        $this->summaryXp = null;
        $this->mode = 'playing';
    }

    public function revealAnswer(): void
    {
        if ($this->mode !== 'playing') {
            return;
        }

        $this->revealed = true;
    }

    public function rateCard(string $rating, FlashcardService $flashcardService): void
    {
        if ($this->mode !== 'playing' || ! $this->revealed) {
            return;
        }

        $card = $this->currentCard;

        if (! $card) {
            return;
        }

        $flashcardService->rateCard($card, FlashcardRating::from($rating));
        $this->reviewedCount++;
        $this->advanceOrFinish($flashcardService);
    }

    public function finishSession(FlashcardService $flashcardService, GamificationService $gamificationService): void
    {
        if ($this->mode !== 'playing') {
            return;
        }

        $duration = max(0, now()->timestamp - $this->sessionStartedAt);
        $reviewed = $this->reviewedCount;

        if ($reviewed > 0) {
            $session = $flashcardService->recordSession(auth()->user(), $reviewed, $duration);
            $this->summaryXp = $session->xp_earned;
        } else {
            $this->summaryXp = 0;
        }

        $this->summaryBaseXp = $reviewed;
        $this->summaryDurationSeconds = $duration;
        $streakInfo = $gamificationService->dailyStreakInfo(auth()->user());
        $this->dailyStreak = $streakInfo['streak'];
        $this->streakMultiplierLabel = $streakInfo['multiplier_label'];
        $this->totalXp = $gamificationService->totalXp(auth()->user());
        $this->dueCount = $flashcardService->dueCount(auth()->user());
        $this->mode = 'finished';
    }

    public function backToSetup(FlashcardService $flashcardService, GamificationService $gamificationService): void
    {
        $this->mode = 'setup';
        $this->cardIds = [];
        $this->currentIndex = 0;
        $this->revealed = false;
        $this->reviewedCount = 0;
        $this->sessionStartedAt = 0;
        $this->summaryDurationSeconds = null;
        $this->summaryXp = null;
        $this->summaryBaseXp = null;
        $this->dueCount = $flashcardService->dueCount(auth()->user());
        $this->activeCount = $flashcardService->activeCount(auth()->user());
        $streakInfo = $gamificationService->dailyStreakInfo(auth()->user());
        $this->dailyStreak = $streakInfo['streak'];
        $this->streakMultiplierLabel = $streakInfo['multiplier_label'];
        $this->totalXp = $gamificationService->totalXp(auth()->user());
    }

    public function seedFromWeakMaterials(FlashcardService $flashcardService): void
    {
        $result = $flashcardService->seedFromWeakMaterials(auth()->user());

        $this->activeCount = $flashcardService->activeCount(auth()->user());
        $this->dueCount = $flashcardService->dueCount(auth()->user());

        if ($result['saved'] === 0) {
            session()->flash('warning', $result['preview'] === 0
                ? 'Belum ada soal dari materi lemah yang bisa disimpan.'
                : 'Semua soal materi lemah sudah ada di Kartu Sakti Anda.');

            return;
        }

        session()->flash('success', "{$result['saved']} kartu dari materi lemah berhasil disimpan ke Kartu Sakti.");
    }

    public function getCurrentCardProperty(): ?Flashcard
    {
        $cardId = $this->cardIds[$this->currentIndex] ?? null;

        if ($cardId === null) {
            return null;
        }

        return Flashcard::query()
            ->with('material')
            ->find($cardId);
    }

    /** @return Collection<int, Flashcard> */
    public function getMostForgottenProperty()
    {
        return app(FlashcardService::class)->mostForgotten(auth()->user());
    }

    /** @return array{preview: int, available: int, skipped: int} */
    public function getWeakSeedPreviewProperty(): array
    {
        return app(FlashcardService::class)->previewWeakMaterialSeed(auth()->user());
    }

    public function render()
    {
        return view('livewire.peserta.kartu-sakti')
            ->layout('layouts.peserta', [
                'activeNav' => 'kartu-sakti',
                'showNav' => in_array($this->mode, ['setup', 'finished'], true),
            ]);
    }

    private function advanceOrFinish(FlashcardService $flashcardService): void
    {
        if ($this->currentIndex >= count($this->cardIds) - 1) {
            $this->finishSession($flashcardService, app(GamificationService::class));

            return;
        }

        $this->currentIndex++;
        $this->revealed = false;
    }
}
