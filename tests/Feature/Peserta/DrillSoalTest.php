<?php

namespace Tests\Feature\Peserta;

use App\DTOs\DrillConfig;
use App\Enums\DrillFocusMode;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamHistoryFilter;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\DrillSetup;
use App\Livewire\Peserta\ExamHistory;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\DrillQuestionGeneratorService;
use App\Services\ExamService;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DrillSoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_drill_setup_page_is_accessible(): void
    {
        $user = $this->createPesertaWithQuestions();

        $this->actingAs($user)
            ->get(route('peserta.drill.index'))
            ->assertOk()
            ->assertSee('Drill Soal')
            ->assertSee('Pilih Sub-materi');
    }

    public function test_start_drill_creates_attempt_and_redirects_to_exam_room(): void
    {
        $user = $this->createPesertaWithQuestions();
        $material = Material::query()->whereHas('subject', fn ($q) => $q->where('code', SubjectCode::Tiu))->firstOrFail();

        Livewire::actingAs($user)
            ->test(DrillSetup::class)
            ->set('subjectCode', 'tiu')
            ->set('selectedMaterialIds', [$material->id])
            ->set('focusMode', 'random')
            ->set('questionCount', 5)
            ->set('durationMinutes', 10)
            ->call('startDrill')
            ->assertRedirect(route('peserta.exam.room', app(ExamService::class)->drillExam()));

        $this->assertDatabaseHas('exam_attempts', [
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Drill->value,
            'status' => ExamAttemptStatus::InProgress->value,
        ]);
    }

    public function test_history_filter_shows_only_drill_attempts(): void
    {
        $user = $this->createPesertaWithQuestions();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd-drill-filter',
            'duration_minutes' => 110,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $drillExam = app(ExamService::class)->drillExam();

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
            'expires_at' => now()->subMinutes(20),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 10,
            'score_tiu' => 10,
            'score_tkp' => 10,
            'total_score' => 30,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $drillExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Drill,
            'drill_config' => [
                'subject_code' => 'tiu',
                'material_ids' => [1],
                'focus_mode' => 'random',
                'question_count' => 5,
                'duration_minutes' => 10,
            ],
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(10),
            'expires_at' => now(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 0,
            'score_tiu' => 10,
            'score_tkp' => 0,
            'total_score' => 10,
        ]);

        Livewire::actingAs($user)
            ->test(ExamHistory::class)
            ->set('typeFilter', ExamHistoryFilter::Drill->value)
            ->assertSee('Drill TIU')
            ->assertDontSee('Simulasi SKD');
    }

    public function test_drill_submit_awards_drill_xp(): void
    {
        $user = $this->createPesertaWithQuestions();
        $material = Material::query()->whereHas('subject', fn ($q) => $q->where('code', SubjectCode::Tiu))->firstOrFail();

        $config = new DrillConfig(
            subjectCode: SubjectCode::Tiu,
            materialIds: [$material->id],
            focusMode: DrillFocusMode::Random,
            questionCount: 2,
            durationMinutes: 10,
        );

        $attempt = app(ExamService::class)->startDrillAttempt($config, $user);

        foreach ($attempt->answers as $answer) {
            $correct = $answer->question->options->firstWhere('is_correct', true);
            $answer->update(['selected_option_id' => $correct?->id]);
        }

        app(ExamService::class)->submitAttempt($attempt->fresh(), $user);

        $this->assertDatabaseHas('xp_rewards', [
            'user_id' => $user->id,
            'source_type' => ExamAttempt::class,
            'source_id' => $attempt->id,
            'amount' => GamificationService::DRILL_XP_REWARD,
        ]);
    }

    private function createPesertaWithQuestions(): User
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        foreach ([SubjectCode::Twk, SubjectCode::Tiu, SubjectCode::Tkp] as $index => $code) {
            $subject = Subject::query()->create([
                'code' => $code,
                'name' => $code->label(),
                'slug' => $code->value,
                'sort_order' => $index + 1,
            ]);

            $material = Material::query()->create([
                'subject_id' => $subject->id,
                'slug' => $code->value.'-materi',
                'name' => 'Materi '.$code->label(),
                'sort_order' => 1,
            ]);

            for ($i = 1; $i <= DrillQuestionGeneratorService::MIN_QUESTIONS; $i++) {
                $question = Question::query()->create([
                    'subject_id' => $subject->id,
                    'material_id' => $material->id,
                    'content' => 'Soal '.$code->value.' #'.$i,
                    'difficulty' => 'easy',
                    'is_active' => true,
                ]);

                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'label' => 'A',
                    'content' => 'Benar',
                    'is_correct' => true,
                    'sort_order' => 1,
                ]);

                QuestionOption::query()->create([
                    'question_id' => $question->id,
                    'label' => 'B',
                    'content' => 'Salah',
                    'is_correct' => false,
                    'sort_order' => 2,
                ]);
            }
        }

        return $user;
    }
}
