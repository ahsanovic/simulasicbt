<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Hasil SKD')]
class SkdResult extends Component
{
    public ExamAttempt $attempt;

    public function mount(ExamAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id() || ! $attempt->event?->is_mode_ujian) {
            abort(403);
        }

        $this->attempt = $attempt->load('event');
    }

    public function continueToNext(): void
    {
        $this->redirect(route('peserta.mode-ujian.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.peserta.mode-ujian.skd-result');
    }
}
