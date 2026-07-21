<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\Dashboard;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\ExamService;
use App\Services\LeaderboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StressTestModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LeaderboardSummaryService::class, function ($mock): void {
            $mock->shouldReceive('getRanks')->andReturn([
                'score' => null,
                'duel' => null,
                'xp' => null,
            ]);
        });
    }

    public function test_dashboard_shows_stress_test_modal_for_exam_without_pin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('startExam', $exam->id)
            ->assertSet('stressTestExamId', $exam->id)
            ->assertSee('Aktifkan Mode Stress-Test');
    }

    public function test_dashboard_skips_stress_test_modal_for_pin_protected_exam(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam(pin: '7K2Q');

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('startExam', $exam->id)
            ->assertSet('pinExamId', $exam->id)
            ->assertSet('stressTestExamId', null);
    }

    public function test_confirm_stress_test_start_creates_attempt_with_selected_mode(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();
        $this->seedQuestionBank();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('startExam', $exam->id)
            ->set('enableStressTest', true)
            ->call('confirmStressTestStart')
            ->assertRedirect(route('peserta.exam.room', $exam));

        $attempt = ExamAttempt::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->stress_test_enabled);
    }

    public function test_confirm_without_stress_test_creates_standard_attempt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();
        $this->seedQuestionBank();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('startExam', $exam->id)
            ->set('enableStressTest', false)
            ->call('confirmStressTestStart')
            ->assertRedirect(route('peserta.exam.room', $exam));

        $attempt = ExamAttempt::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($attempt);
        $this->assertFalse($attempt->stress_test_enabled);
    }

    public function test_start_attempt_stores_stress_test_flag(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();
        $this->seedQuestionBank();

        $attempt = app(ExamService::class)->startAttempt(
            $exam,
            $user,
            stressTestEnabled: true,
        );

        $this->assertTrue($attempt->stress_test_enabled);
    }

    private function createPublishedExam(?string $pin = null): Exam
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return Exam::query()->create([
            'title' => 'Simulasi CPNS',
            'slug' => 'simulasi-cpns-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'pin' => $pin,
            'settings' => [
                'difficulty' => 'all',
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);
    }

    private function seedQuestionBank(): void
    {
        foreach (SubjectCode::cases() as $code) {
            $subject = Subject::query()->create([
                'code' => $code,
                'name' => $code->label(),
                'slug' => $code->value.'-stress-test',
                'sort_order' => 1,
            ]);

            $material = Material::query()->create([
                'subject_id' => $subject->id,
                'slug' => 'materi-'.$code->value.'-stress-test',
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
