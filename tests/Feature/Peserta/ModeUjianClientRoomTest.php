<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\ClientExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Support\ExamDeadline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * Opening the browser exam room (EXAM_CLIENT_ROOM) and moving Mode Ujian
 * participants between it and the Livewire rooms.
 */
class ModeUjianClientRoomTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    public function test_entry_url_follows_the_switch(): void
    {
        config(['exam.client_room' => false]);
        $this->assertSame(route('peserta.exam.room', 5), RoomKinds::entryUrl('skd', 5));
        $this->assertSame(route('peserta.mode-ujian.skb.room'), RoomKinds::entryUrl('skb', null));

        config(['exam.client_room' => true]);
        $this->assertSame(route('peserta.mode-ujian.room', 'skd'), RoomKinds::entryUrl('skd', 5));
        $this->assertSame(route('peserta.mode-ujian.room', 'skb'), RoomKinds::entryUrl('skb', null));
    }

    public function test_with_the_switch_on_the_old_rooms_send_mode_ujian_to_the_browser_room(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertRedirect(route('peserta.mode-ujian.room', 'skd'));

        [$user] = $this->startSkbAttempt();
        Livewire::actingAs($user)->test(SkbExamRoom::class)
            ->assertRedirect(route('peserta.mode-ujian.room', 'skb'));
    }

    public function test_simulasi_attempts_stay_in_the_old_room(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        Livewire::actingAs($ctx['user'])->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertNoRedirect()
            ->assertSee('Soal nomor 1?');
    }

    public function test_with_the_switch_off_the_browser_room_sends_back_to_the_old_room(): void
    {
        config(['exam.client_room' => false]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd'])
            ->assertRedirect(route('peserta.exam.room', $ctx['exam']->id));
    }

    public function test_the_room_loads_every_question_once_without_the_answer_key_and_issues_a_device_token(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $room = Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd']);

        $token = $ctx['attempt']->fresh()->active_device_token;
        $this->assertSame(40, strlen($token));
        $room->assertSeeHtml('data-room')
            ->assertSee($token)
            ->assertSee('Soal nomor 1?')
            ->assertSee('Soal nomor 2?')
            ->assertDontSee('is_correct')
            ->assertDontSee('Pembahasan');
        $this->assertSame([], array_intersect(['room', 'items'], array_keys($room->snapshot['data'])), 'Questions are not kept in the snapshot.');
    }

    public function test_question_html_cannot_break_out_of_the_data_script(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        Question::query()->whereKey($ctx['attempt']->answers()->orderBy('sort_order')->value('question_id'))
            ->update(['content' => '</script><script>alert(1)</script>']);
        cache()->flush();

        $html = Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd'])->html();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
    }

    public function test_an_expired_attempt_is_closed_and_sent_to_the_result_page(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $this->travelTo($ctx['attempt']->expires_at->addMinute());

        Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd'])
            ->assertRedirect(route('peserta.mode-ujian.skd-result', $ctx['attempt']));

        $this->assertNotSame(ExamAttemptStatus::InProgress, $ctx['attempt']->fresh()->status);
    }

    public function test_no_attempt_goes_back_to_the_dashboard(): void
    {
        config(['exam.client_room' => true]);
        [$user, $attempt] = $this->startSkbAttempt();
        $attempt->update(['status' => ExamAttemptStatus::Submitted]);

        Livewire::actingAs($user)->test(ClientExamRoom::class, ['kind' => 'skb'])
            ->assertRedirect(route('peserta.mode-ujian.dashboard'));
    }

    public function test_the_background_deadline_poll_still_works(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd'])
            ->call('checkExpiry')
            ->assertSet('timeUp', false)
            ->assertDispatched('exam-deadline-synced');
    }

    public function test_the_poll_leaves_closing_to_the_browser_within_the_answer_grace(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $room = Livewire::actingAs($ctx['user'])->test(ClientExamRoom::class, ['kind' => 'skd']);

        // The browser's own time-up call brings the unsent answers: a poll
        // landing just after zero must not close the attempt before it.
        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS - 2));
        $room->call('checkExpiry')->assertSet('timeUp', false);
        $this->assertSame(ExamAttemptStatus::InProgress, $ctx['attempt']->fresh()->status);

        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS + 1));
        $room->call('checkExpiry')->assertSet('timeUp', true);
        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
    }

    public function test_the_livescore_sweep_waits_for_the_answer_grace(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS - 2));
        $this->assertFalse(ExamAttempt::query()->expiredButOpen()->exists());

        $this->travelTo($ctx['attempt']->expires_at->addSeconds(ExamDeadline::GRACE_SECONDS + 1));
        $this->assertTrue(ExamAttempt::query()->expiredButOpen()->exists());
    }
}
