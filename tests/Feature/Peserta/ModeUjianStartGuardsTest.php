<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ModeUjian\Dashboard;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\Material;
use App\Models\Question;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\SkbExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Server-side rules for starting a Mode Ujian exam from the PIN dialog —
 * the dashboard buttons are only cosmetics, a crafted Livewire call must
 * hit the same rules.
 */
class ModeUjianStartGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finished_skb_cannot_be_retaken(): void
    {
        [$user] = $this->scenario(EventExamMode::Skb);
        $this->enterPin($user, 'skb', '2222');
        app(SkbExamService::class)->submitAttempt(SkbExamAttempt::where('user_id', $user->id)->first());

        $this->enterPin($user, 'skb', '2222')
            ->assertSet('pinError', 'Anda sudah menyelesaikan ujian SKB. Ujian tidak dapat diulang.')
            ->assertNoRedirect();

        $this->assertSame(1, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_finished_skd_cannot_be_retaken(): void
    {
        $this->seedSkdBank();
        [$user] = $this->scenario(EventExamMode::Skd);
        $this->enterPin($user, 'skd', '1111');
        ExamAttempt::where('user_id', $user->id)->update(['status' => ExamAttemptStatus::Submitted, 'submitted_at' => now()]);

        $this->enterPin($user, 'skd', '1111')
            ->assertSet('pinError', 'Anda sudah menyelesaikan ujian SKD. Ujian tidak dapat diulang.')
            ->assertNoRedirect();

        $this->assertSame(1, ExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_in_progress_exam_can_be_resumed_with_the_pin(): void
    {
        [$user] = $this->scenario(EventExamMode::Skb);
        $this->enterPin($user, 'skb', '2222');

        $this->enterPin($user, 'skb', '2222')->assertRedirect(route('peserta.mode-ujian.skb.room'));

        $this->assertSame(1, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_exam_cannot_start_in_a_draft_session(): void
    {
        [$user, , $session] = $this->scenario(EventExamMode::Skb);
        $session->update(['status' => EventStatus::Draft]);

        $this->enterPin($user, 'skb', '2222')
            ->assertSet('pinError', 'Sesi Anda belum dibuka oleh pengawas. Tunggu instruksi pengawas.')
            ->assertNoRedirect();

        $this->assertSame(0, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_exam_cannot_start_in_a_closed_session(): void
    {
        [$user, , $session] = $this->scenario(EventExamMode::Skb);
        $session->update(['status' => EventStatus::Closed]);

        $this->enterPin($user, 'skb', '2222')
            ->assertSet('pinError', 'Sesi Anda sudah ditutup. Hubungi pengawas.');

        $this->assertSame(0, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_running_exam_can_still_be_resumed_after_session_closes(): void
    {
        [$user, , $session] = $this->scenario(EventExamMode::Skb);
        $this->enterPin($user, 'skb', '2222');
        $session->update(['status' => EventStatus::Closed]);

        $this->enterPin($user, 'skb', '2222')->assertRedirect(route('peserta.mode-ujian.skb.room'));
    }

    public function test_start_error_is_shown_to_the_participant(): void
    {
        [$user, $event] = $this->scenario(EventExamMode::Skb);
        $event->update(['skb_question_count' => 99]);

        $this->enterPin($user, 'skb', '2222')
            ->assertSet('pinError', 'Bank soal SKB jabatan ini hanya punya 3 soal aktif, butuh 99. Hubungi admin.')
            ->assertSee('Bank soal SKB jabatan ini hanya punya 3 soal aktif')
            ->assertNoRedirect();
    }

    public function test_unknown_phase_is_rejected(): void
    {
        [$user] = $this->scenario(EventExamMode::Skb);

        $this->enterPin($user, 'apa-saja', '2222')->assertNoRedirect();

        $this->assertSame(0, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    public function test_repeated_start_never_creates_a_second_attempt(): void
    {
        [$user] = $this->scenario(EventExamMode::Skb);

        $this->enterPin($user, 'skb', '2222');
        $this->enterPin($user, 'skb', '2222');
        $this->enterPin($user, 'skb', '2222');

        $this->assertSame(1, SkbExamAttempt::where('user_id', $user->id)->count());
    }

    private function enterPin(User $user, string $phase, string $pin)
    {
        return Livewire::actingAs($user)->test(Dashboard::class)
            ->call('openPinModal', $phase)
            ->set('pinInput', $pin)
            ->call('submitPin');
    }

    /** @return array{0: User, 1: Event, 2: EventSession} */
    private function scenario(EventExamMode $mode): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = Exam::query()->create([
            'title' => 'Mode Ujian', 'slug' => 'mode-ujian', 'duration_minutes' => 100,
            'status' => ExamStatus::Published, 'settings' => ['difficulty' => 'all'], 'created_by' => $admin->id,
        ]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        for ($i = 1; $i <= 3; $i++) {
            $question = SkbQuestion::query()->create([
                'jabatan_skb_id' => $jabatan->id, 'content' => "<p>Soal {$i}</p>",
                'difficulty' => 'medium', 'is_active' => true, 'created_by' => $admin->id,
            ]);
            foreach (['A', 'B'] as $order => $label) {
                $question->options()->create([
                    'label' => $label, 'content_type' => QuestionOptionContentType::Text,
                    'content' => "Opsi {$label}", 'is_correct' => $label === 'A', 'sort_order' => $order + 1,
                ]);
            }
        }

        $event = Event::query()->create([
            'name' => 'Mode Ujian', 'exam_id' => $exam->id, 'status' => EventStatus::Active, 'created_by' => $admin->id,
            'is_mode_ujian' => true, 'exam_mode' => $mode,
            'skb_question_count' => 3, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);
        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => '1111', 'skb_pin' => '2222', 'status' => EventStatus::Active,
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta', 'nik' => '3500000000000003', 'jabatan_label' => 'Analis', 'jabatan_skb_id' => $jabatan->id,
        ]);

        return [$user, $event, $session];
    }

    private function seedSkdBank(): void
    {
        foreach (SubjectCode::cases() as $code) {
            $subject = Subject::query()->create(['code' => $code, 'name' => $code->label(), 'slug' => $code->value, 'sort_order' => 1]);
            $material = Material::query()->create(['subject_id' => $subject->id, 'slug' => 'm-'.$code->value, 'name' => 'Materi', 'sort_order' => 1]);
            for ($i = 0; $i < ExamQuestionGeneratorService::COUNTS_BY_SUBJECT[$code->value]; $i++) {
                Question::query()->create(['subject_id' => $subject->id, 'material_id' => $material->id, 'content' => "Q{$i}", 'difficulty' => 'medium', 'is_active' => true]);
            }
        }
    }
}
