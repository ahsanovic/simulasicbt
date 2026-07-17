<?php

namespace App\Livewire\Admin\Exams;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Ujian')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public int $duration_minutes = 100;

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public string $status = 'draft';

    public bool $use_pin = false;

    public string $pin = '';

    public string $difficulty = 'all';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:draft,published,archived'],
            'pin' => ['nullable', 'required_if:use_pin,true', 'string', 'size:4', 'regex:/^[A-Z0-9]+$/'],
            'difficulty' => ['required', 'in:all,easy,medium,hard'],
        ];
    }

    public function updatedUsePin(bool $value): void
    {
        if ($value) {
            if ($this->pin === '') {
                $this->pin = Exam::generatePin();
            }
        } else {
            $this->pin = '';
        }

        $this->resetErrorBag('pin');
    }

    public function generatePin(): void
    {
        $this->use_pin = true;
        $this->pin = Exam::generatePin();
        $this->resetErrorBag('pin');
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

    public function openEditModal(int $examId): void
    {
        $exam = Exam::query()->findOrFail($examId);
        $this->editingId = $exam->id;
        $this->title = $exam->title;
        $this->description = $exam->description ?? '';
        $this->duration_minutes = $exam->duration_minutes;
        $this->starts_at = $exam->starts_at?->format('Y-m-d\TH:i');
        $this->ends_at = $exam->ends_at?->format('Y-m-d\TH:i');
        $this->status = $exam->status->value;
        $this->use_pin = filled($exam->pin);
        $this->pin = $exam->pin ?? '';
        $this->difficulty = $exam->settings['difficulty'] ?? 'all';
        $this->showModal = true;
    }

    public function save(ExamQuestionGeneratorService $generator): void
    {
        $validated = $this->validate();

        DB::transaction(function () use ($validated, $generator) {
            $settings = [
                'difficulty' => $validated['difficulty'],
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ];

            $data = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'duration_minutes' => $validated['duration_minutes'],
                'starts_at' => $validated['starts_at'] ?: null,
                'ends_at' => $validated['ends_at'] ?: null,
                'status' => ExamStatus::from($validated['status']),
                'pin' => $this->use_pin ? strtoupper($validated['pin']) : null,
                'settings' => $settings,
                'created_by' => auth()->id(),
            ];

            if ($this->editingId) {
                $exam = Exam::query()->findOrFail($this->editingId);
                unset($data['slug'], $data['created_by']);
                $exam->update($data);
            } else {
                $data['slug'] = Str::slug($validated['title']).'-'.Str::random(4);
                $exam = Exam::query()->create($data);
            }

            if (! $exam->attempts()->exists()) {
                $generator->assertSufficientQuestions($validated['difficulty']);

                $syncData = [];
                foreach ($generator->generate($validated['difficulty']) as $item) {
                    $syncData[$item['id']] = ['sort_order' => $item['sort_order']];
                }

                $exam->questions()->sync($syncData);
            }
        });

        session()->flash('success', 'Ujian berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $examId): void
    {
        Exam::query()->whereKey($examId)->delete();
        session()->flash('success', 'Ujian berhasil dihapus.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'description', 'starts_at', 'ends_at', 'use_pin', 'pin']);
        $this->duration_minutes = 100;
        $this->status = 'draft';
        $this->difficulty = 'all';
        $this->resetValidation();
    }

    public function render(ExamQuestionGeneratorService $generator)
    {
        $exams = Exam::query()
            ->withCount(['questions', 'attempts'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $questionAvailability = $generator->availability($this->difficulty);

        $editingExamHasAttempts = $this->editingId
            ? Exam::query()->whereKey($this->editingId)->whereHas('attempts')->exists()
            : false;

        return view('livewire.admin.exams.index', compact(
            'exams',
            'questionAvailability',
            'editingExamHasAttempts',
        ));
    }
}
