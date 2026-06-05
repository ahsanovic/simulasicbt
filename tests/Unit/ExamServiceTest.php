<?php

namespace Tests\Unit;

use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_attempt_generates_shuffled_questions_per_subject(): void
    {
        $this->seedQuestionBank();
        $exam = $this->createExam();
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $attempt = app(ExamService::class)->startAttempt($exam, $user);
        $answers = $attempt->answers()->with('question.subject')->get();

        $this->assertCount(110, $answers);

        $twkCount = $answers->take(30)->filter(fn ($a) => $a->question->subject->code === SubjectCode::Twk)->count();
        $tiuCount = $answers->slice(30, 35)->filter(fn ($a) => $a->question->subject->code === SubjectCode::Tiu)->count();
        $tkpCount = $answers->slice(65)->filter(fn ($a) => $a->question->subject->code === SubjectCode::Tkp)->count();

        $this->assertSame(30, $twkCount);
        $this->assertSame(35, $tiuCount);
        $this->assertSame(45, $tkpCount);
        $this->assertSame(range(1, 110), $answers->pluck('sort_order')->all());
    }

    public function test_each_attempt_can_have_different_question_order(): void
    {
        $this->seedQuestionBank();
        $exam = $this->createExam();

        $userA = User::factory()->create(['role' => UserRole::Peserta]);
        $userB = User::factory()->create(['role' => UserRole::Peserta]);

        $attemptA = app(ExamService::class)->startAttempt($exam, $userA);
        $attemptB = app(ExamService::class)->startAttempt($exam, $userB);

        $orderA = $attemptA->answers()->orderBy('sort_order')->pluck('question_id')->take(30)->all();
        $orderB = $attemptB->answers()->orderBy('sort_order')->pluck('question_id')->take(30)->all();

        $this->assertNotSame($orderA, $orderB);
    }

    private function seedQuestionBank(): void
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

    private function createExam(): Exam
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return Exam::query()->create([
            'title' => 'Ujian Test',
            'slug' => 'ujian-test',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);
    }
}
