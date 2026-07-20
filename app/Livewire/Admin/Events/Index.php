<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Event Offline')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $exam_id = null;

    public string $status = 'draft';

    public bool $public_livescore = false;

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'status' => ['required', 'in:draft,active,closed'],
            'public_livescore' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $eventId): void
    {
        $event = Event::query()->findOrFail($eventId);
        $this->editingId = $event->id;
        $this->name = $event->name;
        $this->exam_id = $event->exam_id;
        $this->status = $event->status->value;
        $this->public_livescore = (bool) $event->public_livescore;
        $this->description = $event->description ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'exam_id' => $validated['exam_id'],
            'status' => EventStatus::from($validated['status']),
            'public_livescore' => $this->public_livescore,
            'description' => $validated['description'] ?: null,
        ];

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                Event::query()->findOrFail($this->editingId)->update($data);

                return;
            }

            $data['created_by'] = auth()->id();
            $event = Event::query()->create($data);

            // Every event starts with one session so it is usable right away.
            $event->sessions()->create([
                'name' => 'Sesi 1',
                'code' => EventSession::generateUniqueCode(),
                'status' => EventStatus::Draft,
            ]);
        });

        session()->flash('success', 'Event berhasil disimpan.');
        $this->closeModal();
    }

    public function regeneratePublicCode(int $eventId): void
    {
        $event = Event::query()->findOrFail($eventId);
        $event->update(['public_code' => Event::generatePublicCode()]);
        session()->flash('success', 'Link livescore publik diperbarui. Link lama tidak berlaku lagi.');
    }

    public function delete(int $eventId): void
    {
        Event::query()->whereKey($eventId)->delete();
        session()->flash('success', 'Event berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'exam_id', 'description', 'public_livescore']);
        $this->status = 'draft';
        $this->resetValidation();
    }

    public function render()
    {
        $events = Event::query()
            ->with('exam:id,title,duration_minutes')
            ->withCount(['sessions', 'attempts'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $exams = Exam::query()->orderBy('title')->get(['id', 'title']);

        return view('livewire.admin.events.index', compact('events', 'exams'));
    }
}
