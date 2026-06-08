<?php

namespace App\Imports;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class QuestionsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function __construct(private readonly ?int $createdBy = null) {}

    public function collection(Collection $rows): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        DB::transaction(function () use ($rows, $sanitizer) {
            foreach ($rows as $row) {
                $subject = Subject::query()->where('code', $row['subject_code'])->firstOrFail();
                $material = Material::query()
                    ->where('subject_id', $subject->id)
                    ->where('slug', $row['material_slug'])
                    ->firstOrFail();

                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => $sanitizer->sanitize($row['content']),
                    'explanation' => $sanitizer->sanitize($row['explanation'] ?? null),
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                ]);

                $labels = ['a', 'b', 'c', 'd', 'e'];
                $isTkp = $subject->code === SubjectCode::Tkp;

                foreach ($labels as $index => $label) {
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
        });
    }

    public function rules(): array
    {
        return [
            '*.subject_code' => ['required', 'in:twk,tiu,tkp'],
            '*.material_slug' => ['required', 'string'],
            '*.content' => ['required', 'string'],
        ];
    }
}
