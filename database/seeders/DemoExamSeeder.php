<?php

namespace Database\Seeders;

use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Database\Seeder;

class DemoExamSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->first();

        $this->ensureQuestionBank($admin?->id);

        $exam = Exam::query()->updateOrCreate(
            ['slug' => 'simulasi-cbt-demo'],
            [
                'title' => 'Simulasi CBT Demo',
                'description' => 'Ujian demo untuk menguji fitur ruang ujian peserta.',
                'duration_minutes' => 100,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addWeek(),
                'status' => ExamStatus::Published,
                'settings' => [
                    'difficulty' => 'all',
                    'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                    'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
                ],
                'created_by' => $admin?->id,
            ],
        );

        $generator = app(ExamQuestionGeneratorService::class);
        $syncData = [];

        foreach ($generator->generate('all') as $item) {
            $syncData[$item['id']] = ['sort_order' => $item['sort_order']];
        }

        $exam->questions()->sync($syncData);
    }

    private function ensureQuestionBank(?int $createdBy): void
    {
        foreach (ExamQuestionGeneratorService::COUNTS_BY_SUBJECT as $code => $required) {
            $subject = Subject::query()->where('code', $code)->firstOrFail();
            $existing = Question::query()
                ->where('subject_id', $subject->id)
                ->where('is_active', true)
                ->count();

            if ($existing >= $required) {
                continue;
            }

            $material = Material::query()->where('subject_id', $subject->id)->firstOrFail();
            $isTkp = $code === SubjectCode::Tkp->value;
            $needed = $required - $existing;

            for ($i = 1; $i <= $needed; $i++) {
                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => '<p>Soal demo '.strtoupper($code).' #'.($existing + $i).'</p>',
                    'difficulty' => 'medium',
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]);

                $labels = ['A', 'B', 'C', 'D', 'E'];
                foreach ($labels as $optionIndex => $label) {
                    QuestionOption::query()->create([
                        'question_id' => $question->id,
                        'label' => $label,
                        'content_type' => 'text',
                        'content' => 'Pilihan '.$label,
                        'is_correct' => ! $isTkp && $optionIndex === 0,
                        'score_weight' => $isTkp ? (5 - $optionIndex) : null,
                        'sort_order' => $optionIndex + 1,
                    ]);
                }
            }
        }
    }
}
