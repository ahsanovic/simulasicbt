<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\AiReadinessReport;
use App\Livewire\Peserta\Evaluasi;
use App\Models\AiRecommendation;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\LearningPlan;
use App\Models\LearningPlanTask;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamWeaknessAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiReadinessReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_page_shows_ai_readiness_card(): void
    {
        [$user] = $this->createSubmittedAttemptWithQuestions();

        $this->actingAs($user)
            ->get(route('peserta.history'))
            ->assertOk()
            ->assertSee('AI Evaluasi & Rapor Kesiapan CPNS');
    }

    public function test_ai_card_shows_placeholder_when_no_simulations(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->assertSee('Selesaikan simulasi pertama untuk membuka analisis');
    }

    public function test_weakness_stats_are_cached_per_user(): void
    {
        [$user] = $this->createSubmittedAttemptWithQuestions();
        $service = app(ExamWeaknessAnalysisService::class);

        $first = $service->getStatsForUser($user->id);
        Cache::put($service->cacheKey($user->id), ['total_simulations' => 999], now()->addHour());

        $second = $service->getStatsForUser($user->id);

        $this->assertSame(999, $second['total_simulations']);
        $this->assertSame(1, $first['total_simulations']);
    }

    public function test_generate_recommendation_uses_cached_result_without_calling_api(): void
    {
        [$user] = $this->createSubmittedAttemptWithQuestions();
        $stats = app(ExamWeaknessAnalysisService::class)->getStatsForUser($user->id);

        AiRecommendation::query()->create([
            'user_id' => $user->id,
            'recommendation_text' => 'Rekomendasi tersimpan dari cache.',
            'weakness_stats' => $stats,
            'latest_attempt_at' => $stats['latest_attempt_at'],
            'generated_at' => now(),
        ]);

        Http::fake();

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->assertSet('isGenerated', true)
            ->assertSee('Rekomendasi tersimpan dari cache.')
            ->call('generateRecommendation')
            ->assertSet('isGenerated', true)
            ->assertSee('Rekomendasi tersimpan dari cache.');

        Http::assertNothingSent();
    }

    public function test_generate_recommendation_calls_deepseek_when_no_valid_cache(): void
    {
        config([
            'services.deepseek.key' => 'test-deepseek-key',
            'services.deepseek.model' => 'deepseek-chat',
        ]);

        [$user] = $this->createSubmittedAttemptWithQuestions();

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Halo! Fokuskan latihan TWK besok pagi.']],
                ],
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->assertSet('isGenerated', false)
            ->call('generateRecommendation')
            ->assertSet('isGenerated', true)
            ->assertSee('Halo! Fokuskan latihan TWK besok pagi.');

        Http::assertSentCount(1);

        $this->assertDatabaseHas('ai_recommendations', [
            'user_id' => $user->id,
        ]);
    }

    public function test_stale_recommendation_prompts_refresh(): void
    {
        [$user, $attempt] = $this->createSubmittedAttemptWithQuestions();
        $oldStats = app(ExamWeaknessAnalysisService::class)->getStatsForUser($user->id);

        AiRecommendation::query()->create([
            'user_id' => $user->id,
            'recommendation_text' => 'Rekomendasi lama.',
            'weakness_stats' => $oldStats,
            'latest_attempt_at' => now()->subDay(),
            'generated_at' => now()->subDay(),
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $attempt->exam_id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 10,
            'score_tiu' => 10,
            'score_tkp' => 10,
            'total_score' => 30,
        ]);

        app(ExamWeaknessAnalysisService::class)->forget($user->id);

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->assertSet('needsRefresh', true)
            ->assertSet('isGenerated', false)
            ->assertSee('Perbarui Rekomendasi AI');
    }

    public function test_generate_shows_error_when_deepseek_not_configured(): void
    {
        config(['services.deepseek.key' => null]);
        [$user] = $this->createSubmittedAttemptWithQuestions();

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->call('generateRecommendation')
            ->assertSet('isGenerated', false)
            ->assertSee('API key DeepSeek belum dikonfigurasi');
    }

    public function test_generated_state_shows_repeat_simulation_shortcut(): void
    {
        [$user] = $this->createSubmittedAttemptWithQuestions();
        $stats = app(ExamWeaknessAnalysisService::class)->getStatsForUser($user->id);

        AiRecommendation::query()->create([
            'user_id' => $user->id,
            'recommendation_text' => 'Fokus latihan TWK besok pagi.',
            'weakness_stats' => $stats,
            'latest_attempt_at' => $stats['latest_attempt_at'],
            'generated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AiReadinessReport::class)
            ->assertSet('isGenerated', true)
            ->assertSee('Ulangi Simulasi');
    }

    public function test_can_generate_learning_plan_from_evaluation(): void
    {
        [$user] = $this->createSubmittedAttemptWithQuestions();
        $stats = app(ExamWeaknessAnalysisService::class)->getStatsForUser($user->id);

        AiRecommendation::query()->create([
            'user_id' => $user->id,
            'recommendation_text' => 'Fokus latihan materi lemah.',
            'weakness_stats' => $stats,
            'latest_attempt_at' => $stats['latest_attempt_at'],
            'generated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Evaluasi::class)
            ->assertSee('Buat Rencana Otomatis dari Hasil Evaluasi')
            ->call('generatePlanFromEvaluation')
            ->assertRedirect(route('peserta.rencana-belajar.index', [
                'plan' => LearningPlan::query()->where('user_id', $user->id)->value('id'),
            ]));

        $this->assertDatabaseHas('learning_plans', [
            'user_id' => $user->id,
        ]);
        $this->assertGreaterThan(
            0,
            LearningPlanTask::query()
                ->whereHas('plan', fn ($q) => $q->where('user_id', $user->id))
                ->count(),
        );
    }

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function createSubmittedAttemptWithQuestions(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 5,
            'score_tiu' => 0,
            'score_tkp' => 5,
            'total_score' => 10,
        ]);

        $twkSubject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk-'.uniqid(),
            'sort_order' => 1,
        ]);

        $tkpSubject = Subject::query()->create([
            'code' => SubjectCode::Tkp,
            'name' => 'TKP',
            'slug' => 'tkp-'.uniqid(),
            'sort_order' => 3,
        ]);

        $twkMaterial = Material::query()->create([
            'subject_id' => $twkSubject->id,
            'slug' => 'twk-materi-'.uniqid(),
            'name' => 'Nasionalisme',
            'sort_order' => 1,
        ]);

        $tkpMaterial = Material::query()->create([
            'subject_id' => $tkpSubject->id,
            'slug' => 'tkp-materi-'.uniqid(),
            'name' => 'Integritas',
            'sort_order' => 1,
        ]);

        $twkQuestion = Question::query()->create([
            'subject_id' => $twkSubject->id,
            'material_id' => $twkMaterial->id,
            'content' => 'Apa ibu kota Indonesia?',
            'explanation' => 'Pembahasan TWK',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $correctOption = QuestionOption::query()->create([
            'question_id' => $twkQuestion->id,
            'label' => 'A',
            'content' => 'Jakarta',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $twkQuestion->id,
            'label' => 'B',
            'content' => 'Bandung',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        ExamAnswer::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $twkQuestion->id,
            'sort_order' => 1,
            'selected_option_id' => $correctOption->id,
            'answered_at' => now(),
        ]);

        $tkpQuestion = Question::query()->create([
            'subject_id' => $tkpSubject->id,
            'material_id' => $tkpMaterial->id,
            'content' => 'Sikap kerja terbaik adalah?',
            'explanation' => null,
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $bestTkpOption = QuestionOption::query()->create([
            'question_id' => $tkpQuestion->id,
            'label' => 'A',
            'content' => 'Disiplin tinggi',
            'is_correct' => false,
            'score_weight' => 5,
            'sort_order' => 1,
        ]);

        QuestionOption::query()->create([
            'question_id' => $tkpQuestion->id,
            'label' => 'B',
            'content' => 'Kurang disiplin',
            'is_correct' => false,
            'score_weight' => 2,
            'sort_order' => 2,
        ]);

        ExamAnswer::query()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $tkpQuestion->id,
            'sort_order' => 2,
            'selected_option_id' => $bestTkpOption->id,
            'answered_at' => now(),
        ]);

        return [$user, $attempt->fresh()];
    }
}
