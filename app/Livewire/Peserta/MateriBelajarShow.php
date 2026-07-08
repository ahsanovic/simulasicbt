<?php

namespace App\Livewire\Peserta;

use App\Enums\SubjectCode;
use App\Models\Material;
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

    public function render()
    {
        return view('livewire.peserta.materi-belajar-show');
    }
}
