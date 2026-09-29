<?php

namespace Tests\Feature\Public;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Public\LiveScoreShow;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\SkbExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModeUjianPublicLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_skd_only_mode_ujian_event_keeps_the_unchanged_public_board(): void
    {
        [$event] = $this->makeModeUjianEvent(EventExamMode::Skd);

        Livewire::test(LiveScoreShow::class, ['event' => $event])
            ->assertViewHas('category', 'skd');
    }

    public function test_skb_only_public_board_shows_not_started_participant(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        $user = User::factory()->create(['role' => UserRole::Peserta]);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta SKB Publik',
            'nik' => '3201010101010011',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $component = Livewire::test(LiveScoreShow::class, ['event' => $event]);
        $component->assertViewHas('category', 'skb');
        $component->assertSee('Peserta SKB Publik');
        $component->assertSee('Belum Ujian');

        $rows = $component->instance()->rows();
        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['benar']);
    }

    public function test_both_mode_public_board_defaults_to_skd_and_switches_to_skb_via_dropdown(): void
    {
        [$event, $session, $jabatan, $exam] = $this->makeModeUjianEvent(EventExamMode::Both);

        $userB = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Selesai SKD']);
        EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userB->id,
            'name' => 'Peserta Selesai SKD', 'nik' => '3201010101010013', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);
        ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userB->id,
            'started_at' => now()->subMinutes(40), 'submitted_at' => now()->subMinutes(5),
            'expires_at' => now()->subMinutes(5), 'status' => ExamAttemptStatus::Submitted, 'total_score' => 120,
        ]);

        $userC = User::factory()->create(['role' => UserRole::Peserta]);
        $participantC = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $userC->id,
            'name' => 'Peserta SKB Selesai', 'nik' => '3201010101010014', 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);
        SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'event_participant_id' => $participantC->id,
            'user_id' => $userC->id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(20), 'submitted_at' => now(), 'expires_at' => now()->addMinutes(40),
            'status' => ExamAttemptStatus::Submitted, 'correct_score' => 5, 'correct_count' => 9, 'total_score' => 45,
        ]);

        // Default: SKD leaderboard, exam-type picker present since this event supports both.
        $component = Livewire::test(LiveScoreShow::class, ['event' => $event]);
        $component->assertViewHas('category', 'skd');
        $component->assertViewHas('showExamTypePicker', true);
        $component->assertSee('Peserta Selesai SKD');
        $component->assertDontSee('Peserta SKB Selesai');

        $skdRows = collect($component->instance()->rows())->keyBy('name');
        $this->assertSame(120, $skdRows['Peserta Selesai SKD']['score']);

        // Switch to SKB — pure SKB leaderboard, no merged status badges anywhere.
        $component->set('viewMode', 'skb');
        $component->assertViewHas('category', 'skb');
        $component->assertSee('Peserta SKB Selesai');
        $component->assertSee($jabatan->name);
        $component->assertDontSee('TWK');

        $skbRows = collect($component->instance()->rows())->keyBy('name');
        $this->assertSame(45, $skbRows['Peserta SKB Selesai']['score']);
        $this->assertSame($jabatan->name, $skbRows['Peserta SKB Selesai']['jabatan']);
    }

    /**
     * @return array{0: Event, 1: EventSession, 2: JabatanSkb, 3: Exam}
     */
    private function makeModeUjianEvent(EventExamMode $mode): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Mode Ujian Publik',
            'slug' => 'simulasi-mode-ujian-publik-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Livescore Publik',
            'slug' => 'analis-livescore-publik-'.uniqid(),
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'name' => 'Mode Ujian Publik Test',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => $mode,
            'skb_question_count' => 10,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
            'public_livescore' => true,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skd_pin' => $mode->includesSkd() ? '1234' : null,
            'skb_pin' => $mode->includesSkb() ? '5678' : null,
            'status' => EventStatus::Active,
        ]);

        return [$event, $session, $jabatan, $exam];
    }
}
