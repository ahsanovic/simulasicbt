<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Exports\EventResultsExport;
use App\Livewire\Admin\Events\Results;
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
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EventResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_and_event_pages_link_to_hasil_ujian_instead_of_raw_export(): void
    {
        [$admin, $event, $sessionA] = $this->makeBothEvent();

        $this->actingAs($admin)->get(route('admin.events.sessions', $event))
            ->assertOk()
            ->assertSee('Hasil Ujian')
            ->assertSee(e(route('admin.events.results', ['event' => $event, 'sesi' => $sessionA->id])), false)
            ->assertDontSee('Export Semua Sesi');

        $this->actingAs($admin)->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee(e(route('admin.events.results', $event)), false);
    }

    public function test_legacy_offline_event_only_offers_skd(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = $this->makeExam($admin);
        $event = Event::query()->create(['name' => 'Tryout Lama', 'exam_id' => $exam->id, 'status' => EventStatus::Active, 'created_by' => $admin->id]);
        $session = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi 1', 'code' => 'LEG001', 'status' => EventStatus::Active]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Lama']);
        $this->submittedSkd($exam, $event, $session, $peserta, 200);

        Livewire::actingAs($admin)->test(Results::class, ['event' => $event])
            ->assertSet('type', 'skd')
            ->assertViewHas('types', ['skd'])
            ->assertSee('Peserta Lama')
            ->assertSee('TWK');

        // SKB export on an SKD-only event is not a thing.
        $this->actingAs($admin)
            ->get(route('admin.events.results.export', ['event' => $event, 'jenis' => 'skb']))
            ->assertNotFound();
    }

    public function test_both_mode_event_switches_between_skd_and_skb_results(): void
    {
        [$admin, $event, $sessionA, , $jabatan] = $this->makeBothEvent();

        Livewire::actingAs($admin)->test(Results::class, ['event' => $event])
            ->assertSet('type', 'skd')
            ->assertViewHas('types', ['skd', 'skb'])
            ->assertSee('Peserta A1')
            ->assertSee('TWK')
            ->set('type', 'skb')
            ->assertSee('Benar')
            ->assertDontSee('TWK')
            ->assertSee($jabatan->name);
    }

    public function test_results_are_ranked_and_session_filter_narrows_the_list(): void
    {
        [$admin, $event, $sessionA] = $this->makeBothEvent();

        $component = Livewire::actingAs($admin)->test(Results::class, ['event' => $event]);
        $rows = $component->viewData('rows');

        // Highest SKD score first, peserta without an attempt still listed with 0.
        $this->assertSame(['Peserta B1', 'Peserta A1', 'Peserta A2'], $rows->pluck('name')->all());
        $this->assertSame([1, 2, 3], $rows->pluck('rank')->all());
        $this->assertSame('Belum Mulai', $rows->last()['status']);
        $this->assertSame(0, $rows->last()['score']);

        $component->set('sessionId', $sessionA->id);
        $this->assertSame(['Peserta A1', 'Peserta A2'], $component->viewData('rows')->pluck('name')->all());
    }

    public function test_partial_export_contains_only_the_selected_session_and_type(): void
    {
        [, $event, $sessionA, $sessionB] = $this->makeBothEvent();

        $partial = new EventResultsExport($event, 'skb', $sessionA);
        $rows = $partial->collection();

        $this->assertCount(2, $rows);
        $this->assertContains('Benar', $partial->headings());
        $this->assertNotContains('Skor TWK', $partial->headings());
        $this->assertSame(['Sesi A'], $rows->pluck(4)->unique()->values()->all());

        $full = (new EventResultsExport($event, 'skb'))->collection();
        $this->assertCount(3, $full);
        $this->assertEqualsCanonicalizing(['Sesi A', 'Sesi B'], $full->pluck(4)->unique()->values()->all());

        // SKB peserta A1 answered 2 of 3, 1 correct at 5 points (still in progress → live score).
        $a1 = $rows->firstWhere(1, 'Peserta A1');
        $this->assertSame([2, 3, 1, 5], [$a1[5], $a1[6], $a1[7], $a1[8]]);
    }

    public function test_export_route_downloads_partial_and_full_files(): void
    {
        [$admin, $event, $sessionA] = $this->makeBothEvent();
        Excel::fake();

        $this->actingAs($admin)
            ->get(route('admin.events.results.export', ['event' => $event, 'jenis' => 'skd', 'sesi' => $sessionA->id]))
            ->assertOk();

        Excel::assertDownloaded(
            'hasil-skd-'.str('Event Hasil-Sesi A')->slug().'-'.now()->format('Y-m-d').'.xlsx',
            fn (EventResultsExport $export) => $export->collection()->count() === 2,
        );

        $this->actingAs($admin)
            ->get(route('admin.events.results.export', ['event' => $event, 'jenis' => 'skb']))
            ->assertOk();

        Excel::assertDownloaded(
            'hasil-skb-'.str('Event Hasil-semua-sesi')->slug().'-'.now()->format('Y-m-d').'.xlsx',
            fn (EventResultsExport $export) => $export->collection()->count() === 3,
        );
    }

    public function test_export_rejects_a_session_from_another_event(): void
    {
        [$admin, $event] = $this->makeBothEvent();
        [, , $foreignSession] = $this->makeBothEvent('Event Lain');

        $this->actingAs($admin)
            ->get(route('admin.events.results.export', ['event' => $event, 'jenis' => 'skd', 'sesi' => $foreignSession->id]))
            ->assertNotFound();
    }

    /**
     * Both-mode event, two sessions:
     *  - Sesi A: A1 (SKD 150 submitted, SKB in progress 2/3 answered, 1 correct), A2 (nothing started)
     *  - Sesi B: B1 (SKD 300 submitted)
     *
     * @return array{0: User, 1: Event, 2: EventSession, 3: EventSession, 4: JabatanSkb}
     */
    private function makeBothEvent(string $name = 'Event Hasil'): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = $this->makeExam($admin);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis '.$name, 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        $event = Event::query()->create([
            'name' => $name, 'exam_id' => $exam->id, 'status' => EventStatus::Active, 'created_by' => $admin->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Both,
            'skb_question_count' => 3, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);

        $sessionA = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi A', 'code' => EventSession::generateUniqueCode(), 'status' => EventStatus::Active]);
        $sessionB = EventSession::query()->create(['event_id' => $event->id, 'name' => 'Sesi B', 'code' => EventSession::generateUniqueCode(), 'status' => EventStatus::Active]);

        // 14-digit prefix unique per event so a second fixture event doesn't collide on users.nik.
        $nikPrefix = '3201'.str_pad((string) $event->id, 10, '0', STR_PAD_LEFT);

        $a1 = $this->participant($event, $sessionA, $jabatan, 'Peserta A1', $nikPrefix.'01');
        $this->participant($event, $sessionA, $jabatan, 'Peserta A2', $nikPrefix.'02');
        $b1 = $this->participant($event, $sessionB, $jabatan, 'Peserta B1', $nikPrefix.'03');

        $this->submittedSkd($exam, $event, $sessionA, $a1->user, 150);
        $this->submittedSkd($exam, $event, $sessionB, $b1->user, 300);

        $skb = SkbExamAttempt::query()->create([
            'event_id' => $event->id, 'event_session_id' => $sessionA->id, 'event_participant_id' => $a1->id,
            'user_id' => $a1->user_id, 'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(5), 'expires_at' => now()->addMinutes(55),
            'status' => ExamAttemptStatus::InProgress, 'correct_score' => 5,
        ]);

        foreach (range(1, 3) as $i) {
            $question = \App\Models\SkbQuestion::query()->create(['jabatan_skb_id' => $jabatan->id, 'content' => "Soal {$i}", 'difficulty' => 'medium', 'is_active' => true]);
            $right = \App\Models\SkbQuestionOption::query()->create(['skb_question_id' => $question->id, 'label' => 'A', 'content' => 'Benar', 'is_correct' => true, 'sort_order' => 1]);
            $wrong = \App\Models\SkbQuestionOption::query()->create(['skb_question_id' => $question->id, 'label' => 'B', 'content' => 'Salah', 'is_correct' => false, 'sort_order' => 2]);

            \App\Models\SkbExamAnswer::query()->create([
                'skb_exam_attempt_id' => $skb->id, 'skb_question_id' => $question->id, 'sort_order' => $i,
                'selected_option_id' => match ($i) { 1 => $right->id, 2 => $wrong->id, default => null },
            ]);
        }

        return [$admin, $event, $sessionA, $sessionB, $jabatan];
    }

    private function participant(Event $event, EventSession $session, JabatanSkb $jabatan, string $name, string $nik): EventParticipant
    {
        $user = User::factory()->create(['role' => UserRole::Peserta, 'name' => $name, 'nik' => $nik, 'nip' => null]);

        return EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => $name, 'nik' => $nik, 'jabatan_label' => $jabatan->name, 'jabatan_skb_id' => $jabatan->id,
        ]);
    }

    private function submittedSkd(Exam $exam, Event $event, EventSession $session, User $user, int $score): void
    {
        ExamAttempt::query()->create([
            'exam_id' => $exam->id, 'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'started_at' => now()->subMinutes(60), 'submitted_at' => now()->subMinutes(5), 'expires_at' => now()->subMinutes(5),
            'status' => ExamAttemptStatus::Submitted, 'score_twk' => 50, 'score_tiu' => 50, 'score_tkp' => $score - 100, 'total_score' => $score,
        ]);
    }

    private function makeExam(User $admin): Exam
    {
        return Exam::query()->create([
            'title' => 'Simulasi Hasil', 'slug' => 'simulasi-hasil-'.uniqid(), 'duration_minutes' => 100,
            'status' => ExamStatus::Published, 'settings' => ['difficulty' => 'all'], 'created_by' => $admin->id,
        ]);
    }
}
