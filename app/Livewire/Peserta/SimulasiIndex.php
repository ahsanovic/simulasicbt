<?php

namespace App\Livewire\Peserta;

use App\Livewire\Concerns\InteractsWithFullExamStart;
use App\Enums\SkdTarget;
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

    public string $skdTarget = 'cpns';

    public function render(ExamCatalogService $examCatalog, GamificationService $gamificationService)
    {
        $target = SkdTarget::tryFrom($this->skdTarget) ?? SkdTarget::Cpns;
        $userId = (int) auth()->id();

        return view('livewire.peserta.simulasi-index', [
            'exams' => $examCatalog->availableFullSimulationsFor($userId, $target),
            'examPassXpReward' => GamificationService::EXAM_PASS_XP_REWARD,
            'selectedSkdTarget' => $target,
            'skdTargetOptions' => SkdTarget::options(),
            'passingGrades' => exam_passing_grades($target),
        ]);
    }
}
