<?php

namespace Tests\Unit;

use App\Data\ExamResultsExportFilters;
use App\Enums\DuelMatchType;
use App\Enums\DuelSessionStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\DuelSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\ExamResultsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamResultsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulasi_filter_only_returns_full_non_duel_attempts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $simulasiExam = $this->createExam($admin, 'Simulasi SKD', ['difficulty' => 'all']);
        $drillExam = $this->createExam($admin, 'Drill Soal', ['difficulty' => 'all', 'is_drill' => true]);

        $simulasiAttempt = $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full);
        $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Remedial);
        $this->createSubmittedAttempt($peserta, $drillExam, ExamAttemptType::Drill);
        $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full, duelSession: $this->createDuelSession($peserta));

        $ids = ExamResultsQuery::filtered(new ExamResultsExportFilters(examTypeFilter: 'simulasi'))
            ->pluck('id')
            ->all();

        $this->assertSame([$simulasiAttempt->id], $ids);
    }

    public function test_drill_filter_only_returns_drill_attempts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $simulasiExam = $this->createExam($admin, 'Simulasi SKD', ['difficulty' => 'all']);
        $drillExam = $this->createExam($admin, 'Drill Soal', ['difficulty' => 'all', 'is_drill' => true]);

        $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full);
        $drillAttempt = $this->createSubmittedAttempt($peserta, $drillExam, ExamAttemptType::Drill);

        $ids = ExamResultsQuery::filtered(new ExamResultsExportFilters(examTypeFilter: 'drill'))
            ->pluck('id')
            ->all();

        $this->assertSame([$drillAttempt->id], $ids);
    }

    public function test_remedial_filter_only_returns_remedial_attempts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $simulasiExam = $this->createExam($admin, 'Simulasi SKD', ['difficulty' => 'all']);

        $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full);
        $remedialAttempt = $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Remedial);

        $ids = ExamResultsQuery::filtered(new ExamResultsExportFilters(examTypeFilter: 'remedial'))
            ->pluck('id')
            ->all();

        $this->assertSame([$remedialAttempt->id], $ids);
    }

    public function test_duel_filter_returns_duel_session_or_duel_exam_attempts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $simulasiExam = $this->createExam($admin, 'Simulasi SKD', ['difficulty' => 'all']);
        $duelExam = $this->createExam($admin, 'Duel SKD', ['difficulty' => 'all', 'is_duel' => true]);

        $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full);
        $duelBySession = $this->createSubmittedAttempt($peserta, $simulasiExam, ExamAttemptType::Full, duelSession: $this->createDuelSession($peserta));
        $duelByExam = $this->createSubmittedAttempt($peserta, $duelExam, ExamAttemptType::Full);

        $ids = ExamResultsQuery::filtered(new ExamResultsExportFilters(examTypeFilter: 'duel'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$duelBySession->id, $duelByExam->id])->sort()->values()->all(),
            $ids,
        );
    }

    /** @param array<string, mixed> $settings */
    private function createExam(User $admin, string $title, array $settings): Exam
    {
        return Exam::query()->create([
            'title' => $title,
            'slug' => str()->slug($title).'-'.str()->random(4),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => $settings,
            'created_by' => $admin->id,
        ]);
    }

    private function createDuelSession(User $host): DuelSession
    {
        return DuelSession::query()->create([
            'code' => strtoupper(str()->random(6)),
            'host_user_id' => $host->id,
            'question_ids' => [1, 2, 3],
            'status' => DuelSessionStatus::Completed,
            'match_type' => DuelMatchType::Random,
            'duration_minutes' => 10,
        ]);
    }

    private function createSubmittedAttempt(
        User $peserta,
        Exam $exam,
        ExamAttemptType $attemptType,
        ?DuelSession $duelSession = null,
    ): ExamAttempt {
        return ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $peserta->id,
            'attempt_type' => $attemptType,
            'duel_session_id' => $duelSession?->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);
    }
}
