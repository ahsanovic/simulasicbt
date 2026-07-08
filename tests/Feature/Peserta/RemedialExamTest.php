<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ExamHistory;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Models\XpReward;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RemedialExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_locked_remedial_button_when_xp_below_threshold(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithWrongAnswers();

        $this->actingAs($user)
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee('Remedial Otomatis')
            ->assertSee('Butuh 300 XP');
    }

    public function test_history_shows_remedial_button_when_unlocked(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithWrongAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        $this->actingAs($user)
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee('Ujian Remedial (2 soal salah)');
    }

    public function test_start_remedial_from_history_redirects_to_exam_room(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithWrongAnswers();
        $this->grantXp($user, GamificationService::REMEDIAL_UNLOCK_XP);

        Livewire::actingAs($user)
            ->test(ExamHistory::class)
            ->call('startRemedial', $attempt->id)
            ->assertRedirect(route('peserta.exam.room', $attempt->exam_id));

        $this->assertDatabaseHas('exam_attempts', [
            'user_id' => $user->id,
            'parent_attempt_id' => $attempt->id,
            'attempt_type' => ExamAttemptType::Remedial->value,
            'status' => ExamAttemptStatus::InProgress->value,
        ]);
    }

    public function test_history_shows_unlock_modal_when_session_flashed(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->withSession(['show_remedial_unlock_modal' => true])
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee('Selamat! Kemampuan Baru Terbuka!');
    }

    /** @return array{0: User, 1: ExamAttempt} */
    private function createSubmittedAttemptWithWrongAnswers(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd-remedial',
            'duration_minutes' => 110,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 5,
            'score_tiu' => 0,
            'score_tkp' => 5,
            'total_score' => 10,
        ]);

        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'twk-materi',
            'name' => 'Materi TWK',
            'sort_order' => 1,
        ]);

        for ($i = 1; $i <= 2; $i++) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => 'Soal '.$i,
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            $correct = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => 'Benar',
                'is_correct' => true,
                'sort_order' => 1,
            ]);

            $wrong = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'B',
                'content' => 'Salah',
                'is_correct' => false,
                'sort_order' => 2,
            ]);

            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => $i,
                'selected_option_id' => $wrong->id,
                'answered_at' => now(),
            ]);
        }

        return [$user, $attempt->fresh(['exam', 'answers.question', 'answers.selectedOption'])];
    }

    private function grantXp(User $user, int $amount): void
    {
        XpReward::query()->create([
            'user_id' => $user->id,
            'source_type' => 'test_grant',
            'source_id' => $user->id,
            'amount' => $amount,
        ]);
    }
}
