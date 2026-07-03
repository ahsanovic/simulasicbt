<?php

namespace App\Livewire\Admin\Questions;

use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Services\GeneratedQuestionValidator;
use App\Services\HtmlSanitizer;
use App\Services\QuestionGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Generate Soal AI')]
class Generate extends Component
{
    public ?int $subject_id = null;

    public ?int $material_id = null;

    public string $difficulty = 'medium';

    public int $questionCount = 3;

    public const MAX_QUESTIONS_PER_GENERATE = 10;

    /** @var array<int, array<string, mixed>> */
    public array $generatedQuestions = [];

    public ?int $regeneratingIndex = null;

    public function updatedQuestionCount(mixed $value): void
    {
        $this->questionCount = max(1, min(self::MAX_QUESTIONS_PER_GENERATE, (int) $value));
    }

    public function updatedSubjectId(): void
    {
        $this->material_id = null;
    }

    public function generate(QuestionGenerationService $generationService): void
    {
        $this->validateGenerationForm();

        $subject = Subject::query()->findOrFail($this->subject_id);
        $material = Material::query()->findOrFail($this->material_id);

        try {
            $this->generatedQuestions = $generationService->generate(
                $subject,
                $material,
                $this->difficulty,
                $this->questionCount,
            );

            session()->flash('success', count($this->generatedQuestions).' soal berhasil di-generate. Silakan review sebelum approve.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function regenerate(int $index, QuestionGenerationService $generationService): void
    {
        if (! isset($this->generatedQuestions[$index])) {
            return;
        }

        $this->validateGenerationForm();

        $subject = Subject::query()->findOrFail($this->subject_id);
        $material = Material::query()->findOrFail($this->material_id);

        $this->regeneratingIndex = $index;

        try {
            $this->generatedQuestions[$index] = $generationService->generateOne(
                $subject,
                $material,
                $this->difficulty,
            );

            session()->flash('success', 'Soal #'.($index + 1).' berhasil di-regenerate.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        } finally {
            $this->regeneratingIndex = null;
        }
    }

    public function removeQuestion(int $index): void
    {
        if (! isset($this->generatedQuestions[$index])) {
            return;
        }

        unset($this->generatedQuestions[$index]);
        $this->generatedQuestions = array_values($this->generatedQuestions);
    }

    public function approve(int $index, GeneratedQuestionValidator $validator, HtmlSanitizer $sanitizer): void
    {
        if (! isset($this->generatedQuestions[$index])) {
            return;
        }

        $subject = Subject::query()->findOrFail($this->subject_id);
        $question = $this->generatedQuestions[$index];
        $error = $validator->validate($question, $subject->code);

        if ($error !== null) {
            $this->generatedQuestions[$index]['validation_error'] = $error;
            session()->flash('error', 'Soal #'.($index + 1).": {$error}");

            return;
        }

        $this->persistQuestion($question, $subject, $sanitizer);

        unset($this->generatedQuestions[$index]);
        $this->generatedQuestions = array_values($this->generatedQuestions);

        session()->flash('success', 'Soal #'.($index + 1).' berhasil disimpan ke bank soal.');
    }

    public function approveAll(GeneratedQuestionValidator $validator, HtmlSanitizer $sanitizer): void
    {
        if ($this->generatedQuestions === []) {
            session()->flash('error', 'Tidak ada soal untuk di-approve.');

            return;
        }

        $subject = Subject::query()->findOrFail($this->subject_id);
        $savedCount = 0;
        $errors = [];

        foreach ($this->generatedQuestions as $index => $question) {
            $error = $validator->validate($question, $subject->code);

            if ($error !== null) {
                $this->generatedQuestions[$index]['validation_error'] = $error;
                $errors[] = 'Soal #'.($index + 1).': '.$error;

                continue;
            }

            $this->persistQuestion($question, $subject, $sanitizer);
            $savedCount++;
        }

        if ($savedCount > 0) {
            $this->generatedQuestions = array_values(array_filter(
                $this->generatedQuestions,
                fn ($question) => ($question['validation_error'] ?? null) !== null,
            ));

            session()->flash('success', "{$savedCount} soal berhasil disimpan ke bank soal.");
        }

        if ($errors !== []) {
            session()->flash('error', implode(' ', $errors));
        }
    }

    public function clearPreview(): void
    {
        $this->generatedQuestions = [];
    }

    public function refreshValidation(int $index, GeneratedQuestionValidator $validator): void
    {
        if (! isset($this->generatedQuestions[$index])) {
            return;
        }

        $subject = Subject::query()->findOrFail($this->subject_id);
        $this->generatedQuestions[$index]['validation_error'] = $validator->validate(
            $this->generatedQuestions[$index],
            $subject->code,
        );
    }

    private function validateGenerationForm(): void
    {
        $this->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'material_id' => [
                'required',
                Rule::exists('materials', 'id')->where('subject_id', $this->subject_id),
            ],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'questionCount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUESTIONS_PER_GENERATE],
        ], [
            'subject_id.required' => 'Jenis soal wajib dipilih.',
            'material_id.required' => 'Materi wajib dipilih.',
            'questionCount.max' => 'Maksimal '.self::MAX_QUESTIONS_PER_GENERATE.' soal per generate.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function persistQuestion(array $question, Subject $subject, HtmlSanitizer $sanitizer): void
    {
        $isTkp = $subject->code === SubjectCode::Tkp;
        $correctOptionIndex = (int) ($question['correct_option_index'] ?? 0);

        DB::transaction(function () use ($question, $isTkp, $correctOptionIndex, $sanitizer) {
            $savedQuestion = Question::query()->create([
                'subject_id' => $this->subject_id,
                'material_id' => $this->material_id,
                'content' => $sanitizer->sanitize('<p>'.e($question['content']).'</p>'),
                'explanation' => $sanitizer->sanitize($question['explanation'] ?? ''),
                'difficulty' => $question['difficulty'] ?? $this->difficulty,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            foreach ($question['options'] as $optionIndex => $option) {
                QuestionOption::query()->create([
                    'question_id' => $savedQuestion->id,
                    'label' => $option['label'],
                    'content_type' => QuestionOptionContentType::Text,
                    'content' => $sanitizer->sanitize($option['content'] ?? ''),
                    'image_path' => null,
                    'is_correct' => ! $isTkp && $optionIndex === $correctOptionIndex,
                    'score_weight' => $isTkp ? (int) ($option['score_weight'] ?? 1) : null,
                    'sort_order' => $optionIndex + 1,
                ]);
            }
        });
    }

    private function materialsForSelect(?int $subjectId)
    {
        return Material::query()
            ->with('materialGroup')
            ->orderedForSelect()
            ->when($subjectId, fn ($query) => $query->where('materials.subject_id', $subjectId))
            ->get();
    }

    public function render(QuestionGenerationService $generationService)
    {
        $subjects = Subject::query()->orderBy('sort_order')->get();
        $materials = $this->materialsForSelect($this->subject_id);
        $selectedSubject = $this->subject_id ? Subject::query()->find($this->subject_id) : null;
        $isOpenAiConfigured = $generationService->isConfigured();
        $maxQuestionsPerGenerate = self::MAX_QUESTIONS_PER_GENERATE;

        return view('livewire.admin.questions.generate', compact(
            'subjects',
            'materials',
            'selectedSubject',
            'isOpenAiConfigured',
            'maxQuestionsPerGenerate',
        ));
    }
}
