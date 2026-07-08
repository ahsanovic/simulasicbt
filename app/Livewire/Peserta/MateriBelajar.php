<?php

namespace App\Livewire\Peserta;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'materi', 'showNav' => true])]
#[Title('Materi Belajar')]
class MateriBelajar extends Component
{
    public string $activeSubjectCode = 'twk';

    public function mount(): void
    {
        $requested = request()->query('kategori');

        if (is_string($requested) && SubjectCode::tryFrom($requested) !== null) {
            $this->activeSubjectCode = $requested;
        }
    }

    public function setSubject(string $code): void
    {
        if (SubjectCode::tryFrom($code) === null) {
            return;
        }

        $this->activeSubjectCode = $code;
    }

    public function render()
    {
        $subjects = Subject::query()
            ->with([
                'materialGroups.materials.subject',
                'materialGroups.materials.cheatSheet',
                'materials.subject',
                'materials.cheatSheet',
            ])
            ->orderBy('sort_order')
            ->get();

        $activeCode = SubjectCode::from($this->activeSubjectCode);
        $activeSubject = $subjects->firstWhere('code', $activeCode);

        return view('livewire.peserta.materi-belajar', [
            'subjects' => $subjects,
            'activeSubject' => $activeSubject,
            'publishedCount' => $this->countPublished($subjects),
        ]);
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     */
    private function countPublished($subjects): int
    {
        return $subjects->sum(function (Subject $subject): int {
            $materials = $subject->materialGroups->isNotEmpty()
                ? $subject->materialGroups->flatMap->materials
                : $subject->materials;

            return $materials
                ->filter(fn (Material $material) => $material->cheatSheet?->isPublished())
                ->count();
        });
    }
}
