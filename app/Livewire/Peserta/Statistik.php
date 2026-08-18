<?php

namespace App\Livewire\Peserta;

use App\Enums\ScoreTrendPeriod;
use App\Enums\SkdTarget;
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

    public string $scoreTrendPeriod = ScoreTrendPeriod::All->value;

    public string $skdTargetFilter = 'all';

    public function mount(
        ExamWeaknessAnalysisService $weaknessAnalysis,
        DeepSeekRecommendationService $recommendationService,
    ): void {
        $this->initializeAiReadinessReport($weaknessAnalysis, $recommendationService);
    }

    public function setScoreTrendPeriod(string $period): void
    {
        $resolved = ScoreTrendPeriod::tryFrom($period);

        if ($resolved !== null) {
            $this->scoreTrendPeriod = $resolved->value;
        }
    }

    public function setSkdTargetFilter(string $value): void
    {
        if ($value === 'all' || SkdTarget::tryFrom($value) !== null) {
            $this->skdTargetFilter = $value;
            $this->refreshWeaknessStatsForSkdFilter();
        }
    }

    private function refreshWeaknessStatsForSkdFilter(): void
    {
        $skdFilter = $this->skdTargetFilter === 'all'
            ? null
            : SkdTarget::from($this->skdTargetFilter);

        $this->weaknessStats = app(ExamWeaknessAnalysisService::class)->getStatsForUser(
            (int) auth()->id(),
            $skdFilter,
        );
        $this->needsRefresh = true;
        $this->isGenerated = false;
        $this->recommendation = null;
        $this->error = null;
    }

    public function render(PesertaStatisticsService $statistics)
    {
        $period = ScoreTrendPeriod::from($this->scoreTrendPeriod);
        $skdTarget = $this->skdTargetFilter === 'all'
            ? null
            : SkdTarget::from($this->skdTargetFilter);

        return view('livewire.peserta.statistik', [
            'stats' => $statistics->forUser(auth()->user(), $period, $skdTarget),
            'activeScoreTrendPeriod' => $period,
            'scoreTrendPeriods' => ScoreTrendPeriod::options(),
            'skdTargetFilter' => $this->skdTargetFilter,
            'skdTargetOptions' => SkdTarget::filterOptions(),
        ]);
    }
}
