<?php

namespace App\Livewire\Peserta;

use App\Livewire\Concerns\InteractsWithFullExamStart;
use App\Services\ExamCatalogService;
use App\Services\GamificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'simulasi', 'showNav' => true])]
#[Title('Simulasi SKD Penuh')]
class SimulasiIndex extends Component
{
    use InteractsWithFullExamStart;

    public function render(ExamCatalogService $examCatalog, GamificationService $gamificationService)
    {
        $userId = (int) auth()->id();

        return view('livewire.peserta.simulasi-index', [
            'exams' => $examCatalog->availableFullSimulationsFor($userId),
            'examPassXpReward' => GamificationService::EXAM_PASS_XP_REWARD,
        ]);
    }
}
