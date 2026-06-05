<?php

namespace App\Livewire\Admin\Questions;

use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Imports\QuestionsImport;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.admin')]
#[Title('Manajemen Soal')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $subjectFilter = '';

    public string $materialFilter = '';

    public bool $showModal = false;

    public bool $showImportModal = false;

    public ?int $editingId = null;

    public ?int $subject_id = null;

    public ?int $material_id = null;

    public string $content = '';

    public string $explanation = '';

    public string $difficulty = 'medium';

    public bool $is_active = true;

    public array $options = [];

    public array $optionImages = [];

    public int $correctOptionIndex = 0;

    public $importFile;

    public function mount(): void
    {
        $this->resetOptions();
    }

    protected function rules(): array
    {
        // Add score_weight validation for TKP
        $rules = [
            'subject_id' => ['required', 'exists:subjects,id'],
            'material_id' => ['required', 'exists:materials,id'],
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.label' => ['required', 'string', 'max:2'],
            'options.*.content_type' => ['required', 'in:text,image'],
            'options.*.content' => ['nullable', 'string'],
            'options.*.image_path' => ['nullable', 'string'],
            'optionImages.*' => ['nullable', 'image', 'max:5120'],
        ];

        // Optionally handle validation for score_weight if subject is TKP
        if ($this->subject_id) {
            $subject = Subject::find($this->subject_id);
            if ($subject && $subject->code === SubjectCode::Tkp) {
                $rules['options.*.score_weight'] = ['required', 'integer', 'min:1', 'max:5'];
            }
        }

        return $rules;
    }

    public function updatedSubjectId(): void
    {
        $this->material_id = null;
    }

    public function updatedSubjectFilter(): void
    {
        $this->materialFilter = '';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMaterialFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'subjectFilter', 'materialFilter']);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $questionId): void
    {
        $question = Question::query()->with('options')->findOrFail($questionId);
        $this->editingId = $question->id;
        $this->subject_id = $question->subject_id;
        $this->material_id = $question->material_id;
        $this->content = $question->content;
        $this->explanation = $question->explanation ?? '';
        $this->difficulty = $question->difficulty;
        $this->is_active = $question->is_active;
        // Fill options array, always include score_weight value for all options
        $this->options = $question->options->sortBy('sort_order')->map(fn ($option) => [
            'label' => $option->label,
            'content_type' => $option->content_type?->value ?? QuestionOptionContentType::Text->value,
            'content' => $option->content ?? '',
            'image_path' => $option->image_path,
            'is_correct' => $option->is_correct,
            'score_weight' => (int) ($option->score_weight ?? 1),
        ])->values()->toArray();

        $this->optionImages = [];
        $this->correctOptionIndex = max(0, (int) collect($this->options)->search(fn ($option) => $option['is_correct'] ?? false));
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $this->validateOptionContents();

        // Validasi tambahan untuk TKP: score_weight tidak boleh duplikat, range harus 1-5
        $subject = Subject::query()->find($validated['subject_id']);
        $isTkp = $subject && $subject->code === SubjectCode::Tkp;

        if ($isTkp) {
            $scoreWeights = array_map(fn ($opt) => (int) $opt['score_weight'], $validated['options']);

            if (count($scoreWeights) !== count(array_unique($scoreWeights))) {
                session()->flash('error', 'Pada soal TKP, skor setiap opsi tidak boleh duplikat.');
                return;
            }

            sort($scoreWeights);
            if (count($scoreWeights) !== 5 || array_values($scoreWeights) !== [1, 2, 3, 4, 5]) {
                session()->flash('error', 'Pada soal TKP, skor setiap opsi harus unik dan bernilai 1, 2, 3, 4, dan 5.');
                return;
            }
        }

        DB::transaction(function () use ($validated, $isTkp) {
            $questionData = [
                'subject_id' => $validated['subject_id'],
                'material_id' => $validated['material_id'],
                'content' => $validated['content'],
                'explanation' => $validated['explanation'] ?: null,
                'difficulty' => $validated['difficulty'],
                'is_active' => $validated['is_active'],
                'created_by' => auth()->id(),
            ];

            if ($this->editingId) {
                $question = Question::query()->findOrFail($this->editingId);
                $question->update($questionData);
                $question->options()->delete();
            } else {
                $question = Question::query()->create($questionData);
            }

            foreach ($validated['options'] as $index => $option) {
                $contentType = QuestionOptionContentType::from($option['content_type']);
                $imagePath = null;
                $content = null;

                if ($contentType === QuestionOptionContentType::Image) {
                    $existingImagePath = $option['image_path'] ?? null;

                    if (isset($this->optionImages[$index]) && $this->optionImages[$index]) {
                        if ($existingImagePath) {
                            Storage::disk('public')->delete($existingImagePath);
                        }

                        $imagePath = $this->optionImages[$index]->store('question-options', 'public');
                    } else {
                        $imagePath = $existingImagePath;
                    }
                } else {
                    $content = $option['content'] ?? '';
                }

                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'label' => $option['label'],
                    'content_type' => $contentType,
                    'content' => $content,
                    'image_path' => $imagePath,
                    // If TKP, never set is_correct TRUE (should be FALSE for all); if non TKP, only one is_correct true
                    'is_correct' => !$isTkp && $index === $this->correctOptionIndex,
                    // For TKP, always set the score_weight, for NON-TKP null
                    'score_weight' => $isTkp ? ($option['score_weight'] ?? 1) : null,
                    'sort_order' => $index + 1,
                ]);
            }
        });

        session()->flash('success', 'Soal berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $questionId): void
    {
        Question::query()->whereKey($questionId)->delete();
        session()->flash('success', 'Soal berhasil dihapus.');
    }

    public function importQuestions(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        Excel::import(new QuestionsImport(auth()->id()), $this->importFile->getRealPath());

        $this->showImportModal = false;
        $this->importFile = null;
        session()->flash('success', 'Soal berhasil diimpor.');
    }

    public function setOptionScoreWeight(int $index, mixed $value): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        $this->options[$index]['score_weight'] = max(1, min(5, (int) $value));
    }

    public function setOptionType(int $index, string $type): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        $this->options[$index]['content_type'] = $type;

        if ($type === QuestionOptionContentType::Text->value) {
            unset($this->optionImages[$index]);
            $this->options[$index]['image_path'] = null;
        } else {
            $this->options[$index]['content'] = '';
        }
    }

    public function removeOptionImage(int $index): void
    {
        unset($this->optionImages[$index]);

        if (isset($this->options[$index])) {
            $this->options[$index]['image_path'] = null;
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'subject_id', 'material_id', 'content', 'explanation', 'correctOptionIndex', 'optionImages']);
        $this->difficulty = 'medium';
        $this->is_active = true;
        $this->resetOptions();
        $this->resetValidation();
    }

    private function resetOptions(): void
    {
        $this->options = [
            ['label' => 'A', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => true, 'score_weight' => 5],
            ['label' => 'B', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false, 'score_weight' => 4],
            ['label' => 'C', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false, 'score_weight' => 3],
            ['label' => 'D', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false, 'score_weight' => 2],
            ['label' => 'E', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false, 'score_weight' => 1],
        ];
    }

    private function validateOptionContents(): void
    {
        $errors = [];

        foreach ($this->options as $index => $option) {
            $contentType = $option['content_type'] ?? QuestionOptionContentType::Text->value;

            if ($contentType === QuestionOptionContentType::Text->value) {
                if (trim($option['content'] ?? '') === '') {
                    $errors["options.{$index}.content"] = 'Isi pilihan wajib diisi.';
                }

                continue;
            }

            $hasNewImage = isset($this->optionImages[$index]) && $this->optionImages[$index];
            $hasExistingImage = ! empty($option['image_path']);

            if (! $hasNewImage && ! $hasExistingImage) {
                $errors["optionImages.{$index}"] = 'Gambar pilihan wajib diunggah.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function render()
    {
        $subjects = Subject::query()->orderBy('sort_order')->get();
        $materials = Material::query()
            ->when($this->subjectFilter, fn ($q) => $q->where('subject_id', $this->subjectFilter))
            ->orderBy('sort_order')
            ->get();

        $modalMaterials = Material::query()
            ->when($this->subject_id, fn ($q) => $q->where('subject_id', $this->subject_id))
            ->orderBy('sort_order')
            ->get();

        $questions = Question::query()
            ->with(['subject', 'material'])
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->when($this->subjectFilter, fn ($q) => $q->where('subject_id', $this->subjectFilter))
            ->when($this->materialFilter, fn ($q) => $q->where('material_id', $this->materialFilter))
            ->latest()
            ->paginate(10);

        return view('livewire.admin.questions.index', compact('questions', 'subjects', 'materials', 'modalMaterials'));
    }
}
