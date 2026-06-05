<?php

namespace Tests\Unit;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamQuestionGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_exactly_110_questions_in_subject_order(): void
    {
        $this->seedSubjectsAndQuestions();

        $ids = app(ExamQuestionGeneratorService::class)->generate('all');

        $this->assertCount(110, $ids);

        $questions = Question::query()
            ->with('subject')
            ->whereIn('id', $ids->pluck('id'))
            ->get()
            ->sortBy(fn ($q) => $ids->firstWhere('id', $q->id)['sort_order'])
            ->values();

        $twkCount = $questions->take(30)->where(fn ($q) => $q->subject->code === SubjectCode::Twk)->count();
        $tiuCount = $questions->slice(30, 35)->where(fn ($q) => $q->subject->code === SubjectCode::Tiu)->count();
        $tkpCount = $questions->slice(65)->where(fn ($q) => $q->subject->code === SubjectCode::Tkp)->count();

        $this->assertSame(30, $twkCount);
        $this->assertSame(35, $tiuCount);
        $this->assertSame(45, $tkpCount);
    }

    public function test_fails_when_bank_is_insufficient(): void
    {
        $this->expectException(ValidationException::class);

        app(ExamQuestionGeneratorService::class)->generate('all');
    }

    private function seedSubjectsAndQuestions(): void
    {
        foreach (SubjectCode::cases() as $code) {
            $subject = Subject::query()->create([
                'code' => $code,
                'name' => $code->label(),
                'slug' => $code->value,
                'sort_order' => 1,
            ]);

            $material = Material::query()->create([
                'subject_id' => $subject->id,
                'slug' => 'materi-'.$code->value,
                'name' => 'Materi '.$code->label(),
                'sort_order' => 1,
            ]);

            $count = ExamQuestionGeneratorService::COUNTS_BY_SUBJECT[$code->value];

            for ($i = 0; $i < $count; $i++) {
                Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => 'Soal '.$i,
                    'difficulty' => 'medium',
                    'is_active' => true,
                ]);
            }
        }
    }
}
