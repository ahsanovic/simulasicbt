<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public bool $is_mode_ujian = false;

    public string $exam_mode = 'skd';

    public ?int $skb_question_count = null;

    public ?int $skb_correct_score = null;

    public ?int $skb_duration_minutes = null;

    public ?int $sessionCount = 1;

    protected function rules(): array
    {
        $mode = EventExamMode::from($this->exam_mode);

        return [
            'name' => ['required', 'string', 'max:255'],
            'exam_id' => [
                $this->is_mode_ujian && ! $mode->includesSkd() ? 'nullable' : 'required',
                'integer',
                'exists:exams,id',
            ],
            'status' => ['required', 'in:draft,active,closed'],
            'public_livescore' => ['boolean'],
            'description' => ['nullable', 'string'],
            'is_mode_ujian' => ['boolean'],
            'exam_mode' => [Rule::requiredIf($this->is_mode_ujian), 'in:skd,skb,both'],
            'skb_question_count' => [$this->is_mode_ujian && $mode->includesSkb() ? 'required' : 'nullable', 'integer', 'min:1', 'max:200'],
            'skb_correct_score' => [$this->is_mode_ujian && $mode->includesSkb() ? 'required' : 'nullable', 'integer', 'min:1', 'max:100'],
            'skb_duration_minutes' => [$this->is_mode_ujian && $mode->includesSkb() ? 'required' : 'nullable', 'integer', 'min:1', 'max:600'],
            'sessionCount' => [$this->is_mode_ujian && ! $this->editingId ? 'required' : 'nullable', 'integer', 'min:1', 'max:50'],
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
        $this->is_mode_ujian = (bool) $event->is_mode_ujian;
        $this->exam_mode = $event->exam_mode->value;
        $this->skb_question_count = $event->skb_question_count;
        $this->skb_correct_score = $event->skb_correct_score;
        $this->skb_duration_minutes = $event->skb_duration_minutes;
        $this->showModal = true;
    }

    public function updatedIsModeUjian(): void
    {
        $this->resetValidation();
    }

    public function updatedExamMode(): void
    {
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'exam_id' => $validated['exam_id'] ?: null,
            'status' => EventStatus::from($validated['status']),
            'public_livescore' => $this->public_livescore,
            'description' => $validated['description'] ?: null,
            'is_mode_ujian' => $this->is_mode_ujian,
            'exam_mode' => $this->is_mode_ujian ? $validated['exam_mode'] : EventExamMode::Skd->value,
            'skb_question_count' => $validated['skb_question_count'] ?: null,
            'skb_correct_score' => $validated['skb_correct_score'] ?: null,
            'skb_duration_minutes' => $validated['skb_duration_minutes'] ?: null,
        ];

        $mode = EventExamMode::from($this->exam_mode);

        DB::transaction(function () use ($data, $mode) {
            if ($this->editingId) {
                Event::query()->findOrFail($this->editingId)->update($data);

                return;
            }

            $data['created_by'] = auth()->id();
            $event = Event::query()->create($data);

            // Mode Ujian: admin declared how many sessions up front, each
            // gets its own system-generated PIN(s) — never typed by hand.
            // Plain offline events keep the old single-session bootstrap.
            $sessionCount = $this->is_mode_ujian ? max(1, (int) $this->sessionCount) : 1;

            for ($i = 1; $i <= $sessionCount; $i++) {
                $event->sessions()->create([
                    'name' => "Sesi {$i}",
                    'code' => EventSession::generateUniqueCode(),
                    'skd_pin' => $this->is_mode_ujian && $mode->includesSkd() ? EventSession::generateUniquePin('skd_pin') : null,
                    'skb_pin' => $this->is_mode_ujian && $mode->includesSkb() ? EventSession::generateUniquePin('skb_pin') : null,
                    'status' => EventStatus::Draft,
                ]);
            }
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
        $this->reset([
            'editingId', 'name', 'exam_id', 'description', 'public_livescore',
            'is_mode_ujian', 'skb_question_count', 'skb_correct_score', 'skb_duration_minutes',
        ]);
        $this->status = 'draft';
        $this->exam_mode = 'skd';
        $this->sessionCount = 1;
        $this->resetValidation();
    }

    public function render()
    {
        $events = Event::query()
            ->with('exam:id,title,duration_minutes,settings')
            ->withCount(['sessions', 'attempts'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $exams = Exam::query()->orderBy('title')->get(['id', 'title', 'settings']);

        return view('livewire.admin.events.index', compact('events', 'exams'));
    }
}
