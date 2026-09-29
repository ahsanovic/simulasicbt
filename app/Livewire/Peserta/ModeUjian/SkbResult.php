<?php

namespace App\Livewire\Peserta\ModeUjian;

use App\Models\ExamAttempt;
use App\Models\SkbExamAttempt;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['showNav' => false])]
#[Title('Hasil Ujian')]
class SkbResult extends Component
{
    public SkbExamAttempt $attempt;

    public ?ExamAttempt $skdAttempt = null;

    public function mount(SkbExamAttempt $attempt): void
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }

        $this->attempt = $attempt->load('event');

        if ($this->attempt->event->exam_mode->includesSkd()) {
            $this->skdAttempt = ExamAttempt::query()
                ->where('event_id', $this->attempt->event_id)
                ->where('user_id', Auth::id())
                ->latest('id')
                ->first();
        }
    }

    public function render()
    {
        return view('livewire.peserta.mode-ujian.skb-result');
    }
}
