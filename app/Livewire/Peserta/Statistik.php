<?php

namespace App\Livewire\Peserta;

use App\Livewire\Concerns\InteractsWithAiReadinessReport;
use App\Services\DeepSeekRecommendationService;
use App\Services\ExamWeaknessAnalysisService;
use App\Services\PesertaStatisticsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'statistik', 'showNav' => true])]
#[Title('Statistik Saya')]
class Statistik extends Component
{
    use InteractsWithAiReadinessReport;

    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->initializeAiReadinessReport($weaknessAnalysis, $recommendationService);
    }

    public function render(PesertaStatisticsService $statistics)
    {
        return view('livewire.peserta.statistik', [
            'stats' => $statistics->forUser(auth()->user()),
        ]);
    }
}
