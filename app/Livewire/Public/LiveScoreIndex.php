<?php

namespace App\Livewire\Public;

use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Livescore Publik')]
class LiveScoreIndex extends Component
{
    public function render()
    {
        $events = Event::query()
            ->where('public_livescore', true)
            ->with('exam:id,title')
            ->withCount(['sessions', 'attempts'])
            ->latest()
            ->get();

        return view('livewire.public.live-score-index', compact('events'));
    }
}
