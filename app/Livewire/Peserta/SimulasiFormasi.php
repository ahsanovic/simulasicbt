<?php

namespace App\Livewire\Peserta;

use App\Models\Formation;
use App\Services\FormationMatchmakingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'simulasi-formasi', 'showNav' => true])]
#[Title('Simulasi Kelulusan Formasi')]
class SimulasiFormasi extends Component
{
    public string $formationSearch = '';

    public ?int $pendingFormationId = null;

    public bool $showChangeConfirmation = false;

    public function mount(): void
    {
        $formation = auth()->user()->formation;

        if ($formation !== null) {
            $this->formationSearch = $formation->name;
        }
    }

    public function selectFormation(int $formationId): void
    {
        $formation = Formation::query()->findOrFail($formationId);
        $currentFormationId = auth()->user()->formation_id;

        if ($currentFormationId !== null && $currentFormationId !== $formationId) {
            $this->pendingFormationId = $formationId;
            $this->formationSearch = $formation->name;
            $this->showChangeConfirmation = true;

            return;
        }

        $this->applyFormation($formationId, $formation->name);
    }

    public function confirmFormationChange(FormationMatchmakingService $matchmaking): void
    {
        if ($this->pendingFormationId === null) {
            return;
        }

        $formation = Formation::query()->findOrFail($this->pendingFormationId);
        $this->applyFormation($this->pendingFormationId, $formation->name);
        $this->cancelFormationChange();
        session()->flash('success', 'Target jabatan berhasil diperbarui.');
    }

    public function cancelFormationChange(): void
    {
        $this->pendingFormationId = null;
        $this->showChangeConfirmation = false;

        $formation = auth()->user()->fresh()->formation;
        $this->formationSearch = $formation?->name ?? '';
    }

    public function clearFormation(FormationMatchmakingService $matchmaking): void
    {
        $matchmaking->clearFormation(auth()->user());
        $this->formationSearch = '';
        $this->cancelFormationChange();
        session()->flash('success', 'Target jabatan dihapus.');
    }

    public function render(FormationMatchmakingService $matchmaking)
    {
        $user = auth()->user()->load('formation');
        $suggestions = $matchmaking->searchFormations($this->formationSearch);
        $analysis = $matchmaking->analyzeForUser($user);

        return view('livewire.peserta.simulasi-formasi', [
            'suggestions' => $suggestions,
            'analysis' => $analysis,
        ]);
    }

    private function applyFormation(int $formationId, string $formationName): void
    {
        app(FormationMatchmakingService::class)->assignFormation(auth()->user(), $formationId);
        $this->formationSearch = $formationName;
        auth()->user()->refresh();

        if (! $this->showChangeConfirmation) {
            session()->flash('success', 'Target jabatan berhasil disimpan.');
        }
    }
}
