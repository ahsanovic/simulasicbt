<?php

namespace App\Livewire\Peserta;

use App\Enums\DailyActivityType;
use App\Enums\FlashcardSourceType;
use App\Enums\LearningPlanTaskCategory;
use App\Enums\SubjectCode;
use App\Models\Material;
use App\Services\DailyStreakService;
use App\Services\FlashcardService;
use App\Services\LearningPlanService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'materi', 'showNav' => true])]
#[Title('Materi Belajar')]
class MateriBelajarShow extends Component
{
    public Material $material;

    public function mount(string $subjectCode, string $materialSlug): void
    {
        $code = SubjectCode::tryFrom($subjectCode);

        abort_if($code === null, 404);

        $material = Material::query()
            ->whereHas('subject', fn ($query) => $query->where('code', $code))
            ->where('slug', $materialSlug)
            ->with(['subject', 'materialGroup', 'cheatSheet'])
            ->first();

        abort_if($material === null, 404);
        abort_unless($material->cheatSheet?->isPublished(), 404);

        $this->material = $material;
    }

    public function markCheatSheetComplete(
        DailyStreakService $dailyStreakService,
        LearningPlanService $learningPlanService,
    ): void {
        $dailyStreakService->logActivity(
            auth()->user(),
            DailyActivityType::CheatSheet,
            $this->material->id,
        );

        $completed = $learningPlanService->completeMatchingTasks(
            auth()->user(),
            LearningPlanTaskCategory::Materi,
        );

        session()->flash(
            'success',
            $completed > 0
                ? 'Materi ditandai selesai! Tugas di Rencana Belajar ikut tercentang & streak diperbarui.'
                : 'Materi ditandai selesai! Streak harian Anda diperbarui.',
        );
    }

    public function getIsCheatSheetCompletedTodayProperty(): bool
    {
        return app(DailyStreakService::class)->hasCompletedCheatSheetToday(
            auth()->user(),
            $this->material->id,
        );
    }

    public function saveToFlashcard(FlashcardService $flashcardService): void
    {
        try {
            $flashcardService->saveFromMaterial(auth()->user(), $this->material);
            session()->flash('success', 'Materi disimpan ke Kartu Sakti.');
        } catch (ValidationException $exception) {
            session()->flash('warning', $exception->validator->errors()->first('flashcard'));
        }
    }

    public function getIsSavedToFlashcardProperty(): bool
    {
        return app(FlashcardService::class)->isSaved(
            auth()->user(),
            FlashcardSourceType::CheatSheet,
            $this->material->id,
        );
    }

    public function render()
    {
        return view('livewire.peserta.materi-belajar-show');
    }
}
