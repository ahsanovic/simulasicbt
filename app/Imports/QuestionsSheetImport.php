<?php

namespace App\Imports;

use App\Enums\SubjectCode;
use App\Imports\Concerns\ValidatesQuestionImportRows;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionsSheetImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use ValidatesQuestionImportRows;

    /** @var array<string, Subject> */
    private array $subjectCache = [];

    /** @var array<string, Material> */
    private array $materialCache = [];

    public function __construct(
        private readonly ?int $createdBy = null,
    ) {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows): void
    {
        $rows = $this->filterQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $sanitizer = app(HtmlSanitizer::class);

        DB::transaction(function () use ($rows, $sanitizer) {
            foreach ($rows as $row) {
                $subject = $this->resolveSubject($row['subject_code']);
                $material = $this->resolveMaterial($subject, $row['material_slug']);

                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => $sanitizer->sanitize($row['content']),
                    'explanation' => $sanitizer->sanitize($row['explanation'] ?? null),
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                ]);

                $this->createOptions($question, $subject, $row, $sanitizer);
            }
        });
    }

    private function resolveSubject(string $code): Subject
    {
        $normalized = strtolower(trim($code));

        if (! isset($this->subjectCache[$normalized])) {
            $this->subjectCache[$normalized] = Subject::query()
                ->where('code', $normalized)
                ->firstOrFail();
        }

        return $this->subjectCache[$normalized];
    }

    private function resolveMaterial(Subject $subject, string $slug): Material
    {
        $normalized = trim($slug);
        $cacheKey = $subject->id.':'.$normalized;

        if (! isset($this->materialCache[$cacheKey])) {
            $this->materialCache[$cacheKey] = Material::query()
                ->where('subject_id', $subject->id)
                ->where('slug', $normalized)
                ->firstOrFail();
        }

        return $this->materialCache[$cacheKey];
    }

    private function createOptions(Question $question, Subject $subject, Collection|array $row, HtmlSanitizer $sanitizer): void
    {
        $isTkp = $subject->code === SubjectCode::Tkp;

        foreach (['a', 'b', 'c', 'd', 'e'] as $index => $label) {
            $contentKey = "option_{$label}";

            if (empty($row[$contentKey])) {
                continue;
            }

            QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => strtoupper($label),
                'content_type' => 'text',
                'content' => $sanitizer->sanitize($row[$contentKey]),
                'is_correct' => ! $isTkp && strtoupper($row['correct_option'] ?? '') === strtoupper($label),
                'score_weight' => $isTkp ? (int) ($row["weight_{$label}"] ?? 1) : null,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
