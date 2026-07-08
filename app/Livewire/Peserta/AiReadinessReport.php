<?php

namespace App\Livewire\Peserta;

use App\Livewire\Concerns\InteractsWithAiReadinessReport;
use App\Services\DeepSeekRecommendationService;
use App\Services\ExamWeaknessAnalysisService;
use Livewire\Component;

class AiReadinessReport extends Component
{
    use InteractsWithAiReadinessReport;

    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->initializeAiReadinessReport($weaknessAnalysis, $recommendationService);
    }

    public function render()
    {
        return view('livewire.peserta.ai-readiness-report', [
            'repeatExam' => $this->resolveRepeatExam(),
        ]);
    }
}
