<?php

namespace App\Imports;

use App\Enums\QuestionOptionContentType;
use App\Imports\Concerns\ValidatesSkbQuestionImportRows;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionImportJob;
use App\Models\SkbQuestionOption;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Holds the actual row -> database write logic shared by the sync
 * (SkbQuestionsImport) and queued (SkbQuestionsQueuedImport) importers.
 *
 * This is a plain delegate rather than a Maatwebsite\Excel sheet import
 * wrapped via WithMultipleSheets: Excel's sheet-name lookup never matches a
 * CSV upload (CSV always loads as a single sheet named "Worksheet"), and
 * when combined with WithChunkReading it silently skips the mapped sheet
 * import altogether instead of throwing. The top-level import classes
 * implement ToCollection/WithChunkReading directly and forward here.
 */
class SkbQuestionsSheetImport
{
    use ValidatesSkbQuestionImportRows;

    public function __construct(
        private readonly int $jabatanSkbId,
        private readonly ?int $createdBy = null,
        private readonly ?int $importJobId = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $rows = $this->filterSkbQuestionRows($rows);

        if ($rows->isEmpty()) {
            return;
        }

        $sanitizer = app(HtmlSanitizer::class);

        $importedCount = $rows->count();

        DB::transaction(function () use ($rows, $sanitizer) {
            foreach ($rows as $row) {
                $question = SkbQuestion::query()->create([
                    'jabatan_skb_id' => $this->jabatanSkbId,
                    'content' => $sanitizer->sanitize($row['content']),
                    'explanation' => $sanitizer->sanitize($row['explanation'] ?? null),
                    'difficulty' => $this->normalizeSkbDifficulty($row['difficulty'] ?? null) ?? 'medium',
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                ]);

                $this->createOptions($question, $row, $sanitizer);
            }
        });

        if ($this->importJobId) {
            SkbQuestionImportJob::query()->find($this->importJobId)?->advance($importedCount);
        }
    }

    private function createOptions(SkbQuestion $question, Collection|array $row, HtmlSanitizer $sanitizer): void
    {
        $correctOption = strtoupper(trim((string) ($row['correct_option'] ?? '')));

        foreach (['a', 'b', 'c', 'd', 'e'] as $index => $label) {
            $contentKey = "option_{$label}";

            if (empty($row[$contentKey])) {
                continue;
            }

            SkbQuestionOption::query()->create([
                'skb_question_id' => $question->id,
                'label' => strtoupper($label),
                'content_type' => QuestionOptionContentType::Text,
                'content' => $sanitizer->sanitize($row[$contentKey]),
                'is_correct' => $correctOption === strtoupper($label),
                'sort_order' => $index + 1,
            ]);
        }
    }
}
