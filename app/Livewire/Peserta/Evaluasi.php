<?php

namespace App\Livewire\Peserta;

use App\Services\DeepSeekRecommendationService;
use App\Services\ExamWeaknessAnalysisService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.peserta', ['activeNav' => 'evaluasi', 'showNav' => true])]
#[Title('Evaluasi & Rapor Kesiapan')]
class Evaluasi extends AiReadinessReport
{
    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->variant = 'full';

        $focus = request()->query('focus');

        if (is_string($focus) && in_array($focus, ['readiness', 'time-management'], true)) {
            $this->focusHighlight = $focus;
        }

        parent::mount($weaknessAnalysis, $recommendationService);
    }

    public function render()
    {
        return view('livewire.peserta.evaluasi', [
            'repeatExam' => $this->resolveRepeatExam(),
        ]);
    }
}
