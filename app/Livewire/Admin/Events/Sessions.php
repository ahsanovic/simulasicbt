<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventSession;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Kelola Sesi Event')]
class Sessions extends Component
{
    public Event $event;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $status = 'draft';

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public function mount(Event $event): void
    {
        $this->event = $event->load('exam:id,title,duration_minutes');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,active,closed'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->name = 'Sesi '.($this->event->sessions()->count() + 1);
        $this->showModal = true;
    }

    public function openEditModal(int $sessionId): void
    {
        $session = $this->event->sessions()->findOrFail($sessionId);
        $this->editingId = $session->id;
        $this->name = $session->name;
        $this->status = $session->status->value;
        $this->starts_at = $session->starts_at?->format('Y-m-d\TH:i');
        $this->ends_at = $session->ends_at?->format('Y-m-d\TH:i');
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'status' => EventStatus::from($validated['status']),
            'starts_at' => $validated['starts_at'] ?: null,
            'ends_at' => $validated['ends_at'] ?: null,
        ];

        if ($this->editingId) {
            $this->event->sessions()->whereKey($this->editingId)->firstOrFail()->update($data);
        } else {
            $data['code'] = EventSession::generateUniqueCode();
            $this->event->sessions()->create($data);
        }

        session()->flash('success', 'Sesi berhasil disimpan.');
        $this->closeModal();
    }

    public function regenerateCode(int $sessionId): void
    {
        $session = $this->event->sessions()->findOrFail($sessionId);
        $session->update(['code' => EventSession::generateUniqueCode()]);
        session()->flash('success', 'Kode sesi diperbarui.');
    }

    public function cycleStatus(int $sessionId): void
    {
        $session = $this->event->sessions()->findOrFail($sessionId);
        $next = match ($session->status) {
            EventStatus::Draft => EventStatus::Active,
            EventStatus::Active => EventStatus::Closed,
            EventStatus::Closed => EventStatus::Active,
        };
        $session->update(['status' => $next]);
        session()->flash('success', 'Status sesi diubah menjadi '.$next->label().'.');
    }

    public function delete(int $sessionId): void
    {
        $this->event->sessions()->whereKey($sessionId)->delete();
        session()->flash('success', 'Sesi berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'starts_at', 'ends_at']);
        $this->status = 'draft';
        $this->resetValidation();
    }

    public function render()
    {
        $sessions = $this->event->sessions()
            ->withCount('attempts')
            ->latest()
            ->get();

        return view('livewire.admin.events.sessions', compact('sessions'));
    }
}
