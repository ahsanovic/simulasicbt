<?php

namespace App\Livewire\Admin\JabatanSkb;

use App\Enums\QuestionImportStatus;
use App\Enums\QuestionOptionContentType;
use App\Livewire\Concerns\HandlesImportErrorModal;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionImportJob;
use App\Models\SkbQuestionOption;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Soal SKB')]
class SoalIndex extends Component
{
    use HandlesImportErrorModal, WithFileUploads, WithPagination;

    public JabatanSkb $jabatanSkb;

    public string $search = '';

    public bool $showModal = false;

    public bool $showImportModal = false;

    public ?int $dismissedImportJobId = null;

    public bool $showPreviewModal = false;

    public ?int $previewQuestionId = null;

    public ?int $editingId = null;

    public string $content = '';

    public string $explanation = '';

    public string $difficulty = 'medium';

    public bool $is_active = true;

    public array $options = [];

    public array $optionImages = [];

    public $editorImage = null;

    public int $correctOptionIndex = 0;

    public function mount(JabatanSkb $jabatanSkb): void
    {
        $this->jabatanSkb = $jabatanSkb;
        $this->resetOptions();
        $this->mountImportErrorModal();
    }

    protected function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.label' => ['required', 'string', 'max:2'],
            'options.*.content_type' => ['required', 'in:text,image'],
            'options.*.content' => ['nullable', 'string'],
            'options.*.image_path' => ['nullable', 'string', 'regex:/^question-options\/[a-zA-Z0-9_\-\.]+$/'],
            'optionImages.*' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
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

    public function openPreviewModal(int $questionId): void
    {
        $this->previewQuestionId = $questionId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewQuestionId = null;
    }

    public function refreshImportProgress(): void
    {
        // Livewire re-render akan memuat ulang status import terbaru.
    }

    public function dismissImportProgress(int $importJobId): void
    {
        $this->dismissedImportJobId = $importJobId;
    }

    #[Renderless]
    public function processEditorImage(): string
    {
        $this->validate([
            'editorImage' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ], [
            'editorImage.required' => 'Gambar wajib dipilih.',
            'editorImage.image' => 'File harus berupa gambar.',
            'editorImage.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.',
            'editorImage.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);

        $path = $this->editorImage->store('question-content', 'public');
        $this->editorImage = null;

        if (! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'editorImage' => 'File gambar gagal disimpan ke storage.',
            ]);
        }

        return storage_asset($path);
    }

    public function openEditModal(int $questionId): void
    {
        $question = $this->jabatanSkb->questions()->with('options')->findOrFail($questionId);
        $this->editingId = $question->id;
        $this->content = $question->content;
        $this->explanation = $question->explanation ?? '';
        $this->difficulty = $question->difficulty;
        $this->is_active = $question->is_active;
        $this->options = $question->options->sortBy('sort_order')->map(fn ($option) => [
            'label' => $option->label,
            'content_type' => $option->content_type?->value ?? QuestionOptionContentType::Text->value,
            'content' => $option->content ?? '',
            'image_path' => $option->image_path,
            'is_correct' => $option->is_correct,
        ])->values()->toArray();

        $this->optionImages = [];
        $this->correctOptionIndex = max(0, (int) collect($this->options)->search(fn ($option) => $option['is_correct'] ?? false));
        $this->showModal = true;
    }

    public function save(?string $editorContent = null): void
    {
        if ($editorContent !== null) {
            $this->content = $editorContent;
        }

        $validated = $this->validate();
        $this->validateOptionContents();

        $sanitizer = app(HtmlSanitizer::class);

        DB::transaction(function () use ($validated, $sanitizer) {
            $questionData = [
                'jabatan_skb_id' => $this->jabatanSkb->id,
                'content' => $sanitizer->sanitize($validated['content']),
                'explanation' => $sanitizer->sanitize($validated['explanation'] ?: null),
                'difficulty' => $validated['difficulty'],
                'is_active' => $validated['is_active'],
            ];

            $oldImagePaths = [];

            if ($this->editingId) {
                $question = $this->jabatanSkb->questions()->findOrFail($this->editingId);
                $question->update($questionData);

                $oldImagePaths = $question->options()
                    ->whereNotNull('image_path')
                    ->pluck('image_path')
                    ->all();

                SkbQuestionOption::withoutEvents(fn () => $question->options()->delete());
            } else {
                $question = SkbQuestion::query()->create([
                    ...$questionData,
                    'created_by' => auth()->id(),
                ]);
            }

            $savedImagePaths = [];

            foreach ($validated['options'] as $index => $option) {
                $contentType = QuestionOptionContentType::from($option['content_type']);
                $imagePath = null;
                $content = null;

                if ($contentType === QuestionOptionContentType::Image) {
                    $existingImagePath = $option['image_path'] ?? null;

                    if (isset($this->optionImages[$index]) && $this->optionImages[$index]) {
                        $imagePath = $this->optionImages[$index]->store('question-options', 'public');
                    } else {
                        $imagePath = $this->resolveExistingImagePath($existingImagePath);
                    }

                    if ($imagePath) {
                        $savedImagePaths[] = $imagePath;
                    }
                } else {
                    $content = $sanitizer->sanitize($option['content'] ?? '');
                }

                SkbQuestionOption::query()->create([
                    'skb_question_id' => $question->id,
                    'label' => $option['label'],
                    'content_type' => $contentType,
                    'content' => $content,
                    'image_path' => $imagePath,
                    'is_correct' => $index === $this->correctOptionIndex,
                    'sort_order' => $index + 1,
                ]);
            }

            foreach ($oldImagePaths as $oldPath) {
                if (! in_array($oldPath, $savedImagePaths, true)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        });

        session()->flash('success', 'Soal SKB berhasil disimpan.');
        $this->closeModal();
    }

    public function delete(int $questionId): void
    {
        $this->jabatanSkb->questions()->whereKey($questionId)->delete();
        session()->flash('success', 'Soal SKB berhasil dihapus.');
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
        $this->reset(['editingId', 'content', 'explanation', 'correctOptionIndex', 'optionImages', 'editorImage']);
        $this->difficulty = 'medium';
        $this->is_active = true;
        $this->resetOptions();
        $this->resetValidation();
    }

    private function resetOptions(): void
    {
        $this->options = [
            ['label' => 'A', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => true],
            ['label' => 'B', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'C', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'D', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
            ['label' => 'E', 'content_type' => 'text', 'content' => '', 'image_path' => null, 'is_correct' => false],
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

    private function resolveExistingImagePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'options' => 'File gambar yang direferensikan tidak ditemukan.',
            ]);
        }

        return $path;
    }

    private function activeImportJob(): ?SkbQuestionImportJob
    {
        return SkbQuestionImportJob::query()
            ->where('jabatan_skb_id', $this->jabatanSkb->id)
            ->where('user_id', auth()->id())
            ->when($this->dismissedImportJobId, fn ($query) => $query->where('id', '!=', $this->dismissedImportJobId))
            ->where(function ($query) {
                $query->whereIn('status', [
                    QuestionImportStatus::Pending,
                    QuestionImportStatus::Processing,
                ])->orWhere(function ($query) {
                    $query->where('status', QuestionImportStatus::Completed)
                        ->where('completed_at', '>=', now()->subHour());
                })->orWhere(function ($query) {
                    $query->where('status', QuestionImportStatus::Failed)
                        ->where('updated_at', '>=', now()->subHour());
                });
            })
            ->latest()
            ->first();
    }

    public function render()
    {
        $questions = $this->jabatanSkb->questions()
            ->with('options')
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        $previewQuestion = $this->previewQuestionId
            ? $this->jabatanSkb->questions()->with('options')->find($this->previewQuestionId)
            : null;

        $importJob = $this->activeImportJob();

        return view('livewire.admin.jabatan-skb.soal-index', compact('questions', 'previewQuestion', 'importJob'));
    }
}
