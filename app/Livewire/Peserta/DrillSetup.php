<?php

namespace App\Livewire\Peserta;

use App\DTOs\DrillConfig;
use App\Enums\DrillFocusMode;
use App\Enums\SubjectCode;
use App\Models\Material;
use App\Services\DrillQuestionGeneratorService;
use App\Services\ExamService;
use App\Services\ExamWeaknessAnalysisService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'drill', 'showNav' => true])]
#[Title('Drill Soal')]
class DrillSetup extends Component
{
    public string $subjectCode = 'tiu';

    /** @var list<int> */
    public array $selectedMaterialIds = [];

    public string $focusMode = 'mixed';

    public int $questionCount = 20;

    public int $durationMinutes = 30;

    public bool $durationCustomized = false;

    public function mount(ExamWeaknessAnalysisService $weaknessAnalysis): void
    {
        $this->durationMinutes = app(DrillQuestionGeneratorService::class)
            ->suggestedDurationMinutes($this->questionCount);

        $materials = $this->materialsForSubject();

        if ($materials->isNotEmpty() && $this->selectedMaterialIds === []) {
            $this->selectedMaterialIds = $materials->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public function updatedSubjectCode(): void
    {
        $this->selectedMaterialIds = $this->materialsForSubject()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function updatedQuestionCount(): void
    {
        if (! $this->durationCustomized) {
            $this->durationMinutes = app(DrillQuestionGeneratorService::class)
                ->suggestedDurationMinutes($this->questionCount);
        }
    }

    public function updatedDurationMinutes(): void
    {
        $this->durationCustomized = true;
    }

    public function resetDuration(): void
    {
        $this->durationCustomized = false;
        $this->durationMinutes = app(DrillQuestionGeneratorService::class)
            ->suggestedDurationMinutes($this->questionCount);
    }

    public function selectWeakMaterials(ExamWeaknessAnalysisService $weaknessAnalysis): void
    {
        $stats = $weaknessAnalysis->getStatsForUser(auth()->id());
        $weakIds = collect($stats['materials'] ?? [])
            ->filter(fn (array $item) => ($item['subject_code'] ?? '') === $this->subjectCode)
            ->filter(fn (array $item) => ($item['percentage'] ?? 100) < 80)
            ->pluck('material_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($weakIds === []) {
            session()->flash('warning', 'Belum ada data kelemahan untuk subjek ini. Pilih sub-materi secara manual.');

            return;
        }

        $availableIds = $this->materialsForSubject()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->selectedMaterialIds = array_values(array_intersect($weakIds, $availableIds));
    }

    public function startDrill(ExamService $examService): void
    {
        $this->validate([
            'subjectCode' => ['required', 'in:twk,tiu,tkp'],
            'selectedMaterialIds' => ['required', 'array', 'min:1'],
            'selectedMaterialIds.*' => ['integer'],
            'focusMode' => ['required', 'in:weak,random,mixed'],
            'questionCount' => ['required', 'integer', 'min:'.DrillQuestionGeneratorService::MIN_QUESTIONS, 'max:'.DrillQuestionGeneratorService::MAX_QUESTIONS],
            'durationMinutes' => ['required', 'integer', 'min:'.DrillQuestionGeneratorService::MIN_DURATION_MINUTES, 'max:'.DrillQuestionGeneratorService::MAX_DURATION_MINUTES],
        ], [
            'selectedMaterialIds.required' => 'Pilih minimal satu sub-materi.',
            'selectedMaterialIds.min' => 'Pilih minimal satu sub-materi.',
        ]);

        $config = new DrillConfig(
            subjectCode: SubjectCode::from($this->subjectCode),
            materialIds: array_values(array_map('intval', $this->selectedMaterialIds)),
            focusMode: DrillFocusMode::from($this->focusMode),
            questionCount: $this->questionCount,
            durationMinutes: $this->durationMinutes,
        );

        try {
            $examService->startDrillAttempt($config, auth()->user());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Tidak bisa memulai drill soal.';

            $this->addError('drill', $message);

            return;
        }

        $exam = $examService->drillExam();
        $this->redirect(route('peserta.exam.room', $exam), navigate: true);
    }

    public function render(DrillQuestionGeneratorService $generator, ExamWeaknessAnalysisService $weaknessAnalysis)
    {
        $subject = SubjectCode::from($this->subjectCode);
        $materials = $this->materialsForSubject();
        $weaknessStats = collect($weaknessAnalysis->getStatsForUser(auth()->id())['materials'] ?? [])
            ->keyBy('material_id');

        $materialOptions = $materials->map(function (Material $material) use ($generator, $subject, $weaknessStats) {
            $stats = $weaknessStats->get($material->id);
            $available = $generator->availableCount($subject, [$material->id]);
            $weakCount = $generator->weakQuestionCount(auth()->user(), $subject, [$material->id]);

            return [
                'id' => $material->id,
                'display_name' => $material->displayName(),
                'available' => $available,
                'weak_count' => $weakCount,
                'percentage' => $stats['percentage'] ?? null,
                'status_label' => $stats['status_label'] ?? null,
            ];
        });

        $selectedAvailable = $generator->availableCount($subject, $this->selectedMaterialIds);
        $selectedWeak = $generator->weakQuestionCount(auth()->user(), $subject, $this->selectedMaterialIds);
        $suggestedDuration = $generator->suggestedDurationMinutes($this->questionCount);

        return view('livewire.peserta.drill-setup', [
            'materialOptions' => $materialOptions,
            'focusModes' => DrillFocusMode::cases(),
            'questionPresets' => DrillQuestionGeneratorService::QUESTION_PRESETS,
            'selectedAvailable' => $selectedAvailable,
            'selectedWeak' => $selectedWeak,
            'suggestedDuration' => $suggestedDuration,
        ]);
    }

    private function materialsForSubject()
    {
        return Material::query()
            ->with(['materialGroup', 'subject'])
            ->whereHas('subject', fn ($query) => $query->where('code', $this->subjectCode))
            ->orderedForSelect()
            ->get();
    }
}
