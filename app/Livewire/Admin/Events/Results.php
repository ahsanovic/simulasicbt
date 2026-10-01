<?php

namespace App\Livewire\Admin\Events;

use App\Models\Event;
use App\Services\EventResultsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Hasil Ujian Event')]
class Results extends Component
{
    public Event $event;

    #[Url(as: 'jenis')]
    public string $type = '';

    #[Url(as: 'sesi', except: null)]
    public ?int $sessionId = null;

    public string $search = '';

    public function mount(Event $event, EventResultsService $results): void
    {
        $this->event = $event;

        $available = $results->availableTypes($event);

        if (! in_array($this->type, $available, true)) {
            $this->type = $available[0];
        }

        if ($this->sessionId !== null && ! $event->sessions()->whereKey($this->sessionId)->exists()) {
            $this->sessionId = null;
        }
    }

    public function exportUrl(bool $currentSessionOnly): string
    {
        return route('admin.events.results.export', array_filter([
            'event' => $this->event,
            'jenis' => $this->type,
            'sesi' => $currentSessionOnly ? $this->sessionId : null,
        ]));
    }

    public function render(EventResultsService $results)
    {
        $rows = $results->rows($this->event, $this->type, $this->sessionId);

        if (filled($this->search)) {
            $needle = mb_strtolower(trim($this->search));
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['name']), $needle)
                || str_contains(mb_strtolower($row['identifier']), $needle))->values();
        }

        return view('livewire.admin.events.results', [
            'rows' => $rows,
            'types' => $results->availableTypes($this->event),
            'sessions' => $this->event->sessions()->orderBy('name')->get(['id', 'name']),
            'unitLabel' => $this->event->is_mode_ujian ? 'Jabatan' : 'Instansi',
        ]);
    }
}
