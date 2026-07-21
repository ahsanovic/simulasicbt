<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Models\CoinTransaction;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Models\XpReward;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_time_to_a_single_participant(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $target = $attempts[0];
        $before = $target->expires_at;

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 10)
            ->call('addTime', $target->id)
            ->assertHasNoErrors();

        $after = $target->fresh()->expires_at;
        $this->assertTrue($after->greaterThan($before));
        $this->assertEqualsWithDelta(10, $before->diffInMinutes($after), 0.2);

        $this->assertEqualsWithDelta(
            0,
            $attempts[1]->expires_at->diffInMinutes($attempts[1]->fresh()->expires_at),
            0.2,
        );
    }

    public function test_admin_can_add_time_to_all_selected_participants(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 15)
            ->set('selected', [(string) $attempts[0]->id, (string) $attempts[1]->id])
            ->call('addTimeToSelected')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        foreach ([$attempts[0], $attempts[1]] as $attempt) {
            $this->assertEqualsWithDelta(15, $attempt->expires_at->diffInMinutes($attempt->fresh()->expires_at), 0.2);
        }
    }

    public function test_added_time_is_capped_so_remaining_never_exceeds_exam_duration(): void
    {
        // Exam is 100 minutes; participant still has 30 → only 70 may be added.
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $target = $attempts[0];

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 500)
            ->call('addTime', $target->id)
            ->assertHasNoErrors();

        $remainingAfter = $target->fresh()->remainingSeconds() / 60;

        $this->assertLessThanOrEqual(100.5, $remainingAfter, 'Sisa waktu tidak boleh melebihi durasi ujian.');
        $this->assertGreaterThan(95, $remainingAfter, 'Waktu tetap ditambahkan sampai batas maksimal.');
    }

    public function test_popup_reports_the_maximum_minutes_that_can_be_added(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();

        $ctx = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->call('openAddTime', $attempts[0]->id)
            ->assertSet('showAddTimeModal', true)
            ->get('addTimeContext');

        $this->assertSame(100, $ctx['duration']);
        $this->assertSame(30, $ctx['remaining']);
        $this->assertSame(70, $ctx['max']);
    }

    public function test_cannot_add_time_when_remaining_already_equals_exam_duration(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $target = $attempts[0];
        $target->update(['expires_at' => now()->addMinutes(100)]);
        $before = $target->fresh()->expires_at;

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 10)
            ->call('addTime', $target->id);

        $this->assertEqualsWithDelta(0, $before->diffInMinutes($target->fresh()->expires_at), 0.2);
    }

    public function test_livescore_closes_attempts_whose_time_ran_out(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $stuck = $attempts[0];

        // Participant went offline, so their in-exam expiry poll never fired.
        $stuck->update(['expires_at' => now()->subMinutes(5)]);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('rows');

        $row = collect($rows)->firstWhere('attempt_id', $stuck->id);
        $this->assertFalse($row['in_progress'], 'Status harus otomatis jadi Selesai saat waktu habis.');

        $fresh = $stuck->fresh();
        $this->assertNotSame(ExamAttemptStatus::InProgress, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertNotNull($fresh->total_score, 'Skor akhir harus dihitung saat ditutup.');
    }

    public function test_closing_an_expired_attempt_records_the_real_expiry_time(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $stuck = $attempts[0];
        $expiredAt = now()->subMinutes(12);
        $stuck->update(['expires_at' => $expiredAt]);

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('rows');

        // Finish time is when the exam actually ran out, not when we noticed.
        $this->assertEqualsWithDelta(0, $expiredAt->diffInSeconds($stuck->fresh()->submitted_at), 2);
    }

    public function test_summary_counts_expired_participants_as_finished(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $attempts[0]->update(['expires_at' => now()->subMinute()]);

        $summary = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('summary');

        // 1 still running, 2 finished (1 submitted earlier + 1 just expired).
        $this->assertSame(1, $summary['in_progress']);
        $this->assertSame(2, $summary['finished']);
    }

    public function test_livescore_rows_expose_per_subject_scores(): void
    {
        [$admin, $event, $session, , $finished] = $this->createSessionWithAttempts();
        $finished->update(['score_twk' => 70, 'score_tiu' => 80, 'score_tkp' => 90, 'total_score' => 240]);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('rows');

        $row = collect($rows)->firstWhere('attempt_id', $finished->id);

        $this->assertSame(70, $row['twk']);
        $this->assertSame(80, $row['tiu']);
        $this->assertSame(90, $row['tkp']);
        $this->assertSame(240, $row['score']);
    }

    public function test_adding_time_skips_finished_participants(): void
    {
        [$admin, $event, $session, $attempts, $finished] = $this->createSessionWithAttempts();
        $finishedExpiry = $finished->expires_at;

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('addMinutes', 10)
            ->set('selected', [(string) $attempts[0]->id, (string) $finished->id])
            ->call('addTimeToSelected')
            ->assertHasNoErrors();

        // In-progress got time; the finished one is left untouched.
        $this->assertEqualsWithDelta(10, $attempts[0]->expires_at->diffInMinutes($attempts[0]->fresh()->expires_at), 0.2);
        $this->assertEqualsWithDelta(0, $finishedExpiry->diffInMinutes($finished->fresh()->expires_at), 0.2);
    }

    public function test_livescore_only_shows_its_own_session(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();

        // A second session with its own participant must not appear in the first session's livescore.
        $otherSession = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 2',
            'code' => 'OTHER1',
            'status' => EventStatus::Active,
        ]);
        $otherPeserta = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Sesi Dua']);
        $otherAttempt = ExamAttempt::query()->create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $otherSession->id,
            'user_id' => $otherPeserta->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->get('rows');

        $attemptIds = array_column($rows, 'attempt_id');
        $this->assertContains($attempts[0]->id, $attemptIds);
        $this->assertNotContains($otherAttempt->id, $attemptIds);
        $this->assertCount(3, $rows); // 2 in-progress + 1 finished from this session only
    }

    public function test_select_all_includes_finished_attempts_so_they_can_be_reset(): void
    {
        [$admin, $event, $session, $attempts, $finished] = $this->createSessionWithAttempts();

        $selected = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('selectAll', true)
            ->get('selected');

        $this->assertContains((string) $attempts[0]->id, $selected);
        $this->assertContains((string) $finished->id, $selected);
    }

    public function test_resetting_a_finished_participant_restarts_their_exam(): void
    {
        [$admin, $event, $session, , $finished] = $this->createSessionWithAttempts();
        $this->seedQuestionBank();

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->call('resetAttempt', $finished->id)
            ->assertHasNoErrors();

        $fresh = $finished->fresh();

        $this->assertSame(ExamAttemptStatus::InProgress, $fresh->status);
        $this->assertNull($fresh->submitted_at);
        $this->assertNull($fresh->total_score);
        $this->assertTrue($fresh->expires_at->isFuture(), 'Timer harus dimulai ulang.');
        $this->assertGreaterThan(0, $fresh->answers()->count(), 'Soal baru harus dibuat.');
        $this->assertSame(0, $fresh->answers()->whereNotNull('selected_option_id')->count(), 'Jawaban lama harus terhapus.');

        // Still exactly one attempt for this participant — one livescore row.
        $this->assertSame(1, ExamAttempt::query()
            ->where('event_session_id', $session->id)
            ->where('user_id', $fresh->user_id)
            ->count());
    }

    public function test_reset_can_be_applied_in_bulk_to_selected_participants(): void
    {
        [$admin, $event, $session, $attempts, $finished] = $this->createSessionWithAttempts();
        $this->seedQuestionBank();

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->set('selected', [(string) $attempts[0]->id, (string) $finished->id])
            ->call('resetSelected')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        foreach ([$attempts[0], $finished] as $attempt) {
            $fresh = $attempt->fresh();
            $this->assertSame(ExamAttemptStatus::InProgress, $fresh->status);
            $this->assertTrue($fresh->expires_at->isFuture());
        }
    }

    public function test_reset_clears_xp_and_coins_awarded_for_that_attempt(): void
    {
        [$admin, $event, $session, , $finished] = $this->createSessionWithAttempts();
        $this->seedQuestionBank();

        XpReward::query()->create([
            'user_id' => $finished->user_id,
            'source_type' => ExamAttempt::class,
            'source_id' => $finished->id,
            'amount' => 50,
        ]);
        CoinTransaction::query()->create([
            'user_id' => $finished->user_id,
            'source_type' => ExamAttempt::class,
            'source_id' => $finished->id,
            'amount' => 10,
            'reason' => 'Reward ikut simulasi',
        ]);

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->call('resetAttempt', $finished->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('xp_rewards', ['source_type' => ExamAttempt::class, 'source_id' => $finished->id]);
        $this->assertDatabaseMissing('coin_transactions', ['source_type' => ExamAttempt::class, 'source_id' => $finished->id]);
    }

    public function test_an_expired_attempt_is_closed_and_recovered_via_reset_not_extra_time(): void
    {
        [$admin, $event, $session, $attempts] = $this->createSessionWithAttempts();
        $this->seedQuestionBank();
        $target = $attempts[0];
        $target->update(['expires_at' => now()->subMinutes(3)]);

        $component = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session]);

        // Opening the livescore closes it out, so extra time no longer applies.
        $this->assertNotSame(ExamAttemptStatus::InProgress, $target->fresh()->status);

        $component->set('addMinutes', 5)->call('addTime', $target->id);
        $this->assertFalse($target->fresh()->expires_at->isFuture(), 'Waktu tidak boleh ditambahkan ke ujian yang sudah ditutup.');

        // Reset is the way back in.
        $component->call('resetAttempt', $target->id)->assertHasNoErrors();

        $revived = $target->fresh();
        $this->assertSame(ExamAttemptStatus::InProgress, $revived->status);
        $this->assertTrue($revived->expires_at->isFuture());
    }

    /**
     * Reset regenerates a full question set, so the bank must be sufficient.
     */
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

    /**
     * @return array{0: User, 1: Event, 2: EventSession, 3: array<int, ExamAttempt>, 4: ExamAttempt}
     */
    private function createSessionWithAttempts(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Event',
            'slug' => 'simulasi-event',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Offline',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'XYZ789',
            'status' => EventStatus::Active,
        ]);

        $attempts = [];

        foreach (range(0, 1) as $i) {
            $peserta = User::factory()->create(['role' => UserRole::Peserta]);
            $attempts[$i] = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'event_id' => $event->id,
                'event_session_id' => $session->id,
                'user_id' => $peserta->id,
                'started_at' => now()->subMinutes(10),
                'expires_at' => now()->addMinutes(30),
                'status' => ExamAttemptStatus::InProgress,
            ]);
        }

        $finishedPeserta = User::factory()->create(['role' => UserRole::Peserta]);
        $finished = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $finishedPeserta->id,
            'started_at' => now()->subMinutes(40),
            'submitted_at' => now()->subMinutes(5),
            'expires_at' => now()->subMinutes(5),
            'status' => ExamAttemptStatus::Submitted,
            'total_score' => 100,
        ]);

        return [$admin, $event, $session, $attempts, $finished];
    }
}
