<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\SkdTarget;
use App\Enums\UserRole;
use App\Livewire\Peserta\ExamHistory;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamHistoryTest extends TestCase
{
    use RefreshDatabase;
    public function test_skd_target_filter_limits_history_to_selected_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $cpnsExam = Exam::query()->create([
            'title' => 'Simulasi CPNS Riwayat',
            'slug' => 'simulasi-cpns-riwayat-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::Cpns->value,
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        $kedinasanExam = Exam::query()->create([
            'title' => 'Simulasi Kedinasan Riwayat',
            'slug' => 'simulasi-kedinasan-riwayat-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::SekolahKedinasan->value,
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $cpnsExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::Cpns,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'expires_at' => now()->subDays(1),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $kedinasanExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::SekolahKedinasan,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'expires_at' => now(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 160,
            'total_score' => 315,
        ]);

        Livewire::actingAs($user)
            ->test(ExamHistory::class)
            ->assertSee('Jenis Simulasi SKD')
            ->assertSee('Simulasi CPNS Riwayat')
            ->assertSee('Simulasi Kedinasan Riwayat')
            ->call('setSkdTargetFilter', SkdTarget::SekolahKedinasan->value)
            ->assertSee('Standar Sekolah Kedinasan')
            ->assertSee('Simulasi Kedinasan Riwayat')
            ->assertDontSee('Simulasi CPNS Riwayat')
            ->call('setSkdTargetFilter', 'all')
            ->assertSee('Ambang batas mengikuti jenis simulasi')
            ->assertSee('Simulasi CPNS Riwayat')
            ->assertSee('Simulasi Kedinasan Riwayat');
    }

    public function test_skd_target_filter_shows_empty_state_when_no_matching_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $cpnsExam = Exam::query()->create([
            'title' => 'Simulasi CPNS Riwayat',
            'slug' => 'simulasi-cpns-riwayat-empty-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => [
                'difficulty' => 'all',
                'skd_target' => SkdTarget::Cpns->value,
            ],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $cpnsExam->id,
            'user_id' => $user->id,
            'attempt_type' => ExamAttemptType::Full,
            'skd_target' => SkdTarget::Cpns,
            'status' => ExamAttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay(),
            'expires_at' => now(),
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        Livewire::actingAs($user)
            ->test(ExamHistory::class)
            ->call('setSkdTargetFilter', SkdTarget::SekolahKedinasan->value)
            ->assertSee('Tidak ada riwayat Sekolah Kedinasan')
            ->assertSee('Semua Jenis SKD');
    }
}
