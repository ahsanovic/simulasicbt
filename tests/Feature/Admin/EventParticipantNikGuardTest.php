<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\Participants;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One NIK is one participant in one session of one open offline event: the
 * import and the manual form refuse a second session, a NIK twice in a file,
 * and a NIK still in another event that is not closed (draft counts as open).
 */
class EventParticipantNikGuardTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '1111111111111111';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_a_nik_already_in_another_session_of_this_event_is_rejected(): void
    {
        $event = $this->makeEvent('Event A');
        $pagi = $this->makeSession($event, 'Sesi Pagi');
        $this->makeSession($event, 'Sesi Siang');
        $this->enroll($event, $pagi, self::NIK);

        $this->import($event, ['Peserta Satu,'.self::NIK.',Analis,Sesi Siang'])
            ->assertSessionHas('import_errors', fn (array $report) => $this->reportSays($report, 'sudah terdaftar di sesi "Sesi Pagi"'));

        $this->assertSame($pagi->id, $event->participants()->sole()->event_session_id);
    }

    public function test_re_importing_a_nik_into_its_own_session_updates_it(): void
    {
        $event = $this->makeEvent('Event A');
        $pagi = $this->makeSession($event, 'Sesi Pagi');
        $this->enroll($event, $pagi, self::NIK);

        $this->import($event, ['Nama Baru,'.self::NIK.',Analis,Sesi Pagi'])->assertSessionHas('success');

        $participant = $event->participants()->sole();
        $this->assertSame('Nama Baru', $participant->name);
        $this->assertSame($pagi->id, $participant->event_session_id);
    }

    public function test_a_nik_still_in_an_active_or_draft_event_is_rejected(): void
    {
        foreach (['active', 'draft'] as $status) {
            $other = $this->makeEvent("Event Lama {$status}", $status);
            $nik = $status === 'active' ? '2222222222222222' : '3333333333333333';
            $this->enroll($other, $this->makeSession($other, 'Sesi 1'), $nik);

            $event = $this->makeEvent("Event Baru {$status}");
            $this->makeSession($event, 'Sesi 1');

            $this->import($event, ["Peserta,{$nik},Analis,Sesi 1"])
                ->assertSessionHas('import_errors', fn (array $report) => $this->reportSays($report, "event \"Event Lama {$status}\" yang belum ditutup"));

            $this->assertSame(0, $event->participants()->count());
        }
    }

    public function test_a_nik_from_a_closed_event_is_imported_with_the_same_login(): void
    {
        $old = $this->makeEvent('Event Lama', 'closed');
        $oldParticipant = $this->enroll($old, $this->makeSession($old, 'Sesi 1'), self::NIK);

        $event = $this->makeEvent('Event Baru');
        $this->makeSession($event, 'Sesi 1');

        $this->import($event, ['Peserta,'.self::NIK.',Analis,Sesi 1'])->assertSessionHas('success');

        $this->assertSame($oldParticipant->user_id, $event->participants()->sole()->user_id);
        $this->assertSame(1, User::query()->where('nik', self::NIK)->count());
    }

    public function test_the_same_nik_twice_in_one_file_is_rejected(): void
    {
        $event = $this->makeEvent('Event A');
        $this->makeSession($event, 'Sesi Pagi');
        $this->makeSession($event, 'Sesi Siang');

        $this->import($event, [
            'Peserta,'.self::NIK.',Analis,Sesi Pagi',
            'Peserta,'.self::NIK.',Analis,Sesi Siang',
        ])->assertSessionHas('import_errors', fn (array $report) => $this->reportSays($report, 'NIK sama dengan baris 2'));

        $this->assertSame(0, $event->participants()->count());
    }

    public function test_the_manual_form_follows_the_same_rules(): void
    {
        $formation = Formation::create(['name' => 'Analis', 'slug' => 'analis', 'group' => 'umum']);

        $other = $this->makeEvent('Event Lain');
        $this->enroll($other, $this->makeSession($other, 'Sesi 1'), '2222222222222222');

        $event = $this->makeEvent('Event A');
        $pagi = $this->makeSession($event, 'Sesi Pagi');
        $siang = $this->makeSession($event, 'Sesi Siang');
        $this->enroll($event, $pagi, self::NIK);

        $form = fn (string $nik, int $sessionId) => Livewire::actingAs($this->admin)
            ->test(Participants::class, ['event' => $event])
            ->call('openCreateModal')
            ->set('editName', 'Peserta')
            ->set('editNik', $nik)
            ->set('editSessionId', $sessionId)
            ->set('editFormationId', $formation->id)
            ->call('saveForm');

        $form(self::NIK, $siang->id)->assertHasErrors(['editNik']);
        $form('2222222222222222', $pagi->id)->assertHasErrors(['editNik']);
        $form(self::NIK, $pagi->id)->assertHasNoErrors();

        $this->assertSame(1, $event->participants()->count());
        $this->assertSame($pagi->id, $event->participants()->sole()->event_session_id);
    }

    public function test_editing_a_nik_to_one_used_by_another_account_is_rejected(): void
    {
        $formation = Formation::create(['name' => 'Analis', 'slug' => 'analis', 'group' => 'umum']);
        $event = $this->makeEvent('Event A');
        $pagi = $this->makeSession($event, 'Sesi Pagi');
        $this->enroll($event, $pagi, self::NIK);
        $second = $this->enroll($event, $pagi, '2222222222222222');

        Livewire::actingAs($this->admin)
            ->test(Participants::class, ['event' => $event])
            ->call('openEditModal', $second->id)
            ->set('editNik', self::NIK)
            ->set('editFormationId', $formation->id)
            ->call('saveForm')
            ->assertHasErrors(['editNik']);

        $this->assertSame('2222222222222222', $second->fresh()->nik);
    }

    private function makeEvent(string $name, string $status = 'active'): Event
    {
        return Event::create([
            'name' => $name, 'code' => Event::generateUniqueCode(),
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd, 'status' => $status,
        ]);
    }

    private function makeSession(Event $event, string $name): EventSession
    {
        return EventSession::create([
            'event_id' => $event->id, 'name' => $name,
            'code' => EventSession::generateUniqueCode(), 'skd_pin' => '1111', 'status' => 'active',
        ]);
    }

    private function enroll(Event $event, EventSession $session, string $nik): EventParticipant
    {
        $user = User::query()->where('nik', $nik)->first()
            ?? User::factory()->create(['role' => UserRole::Peserta, 'nik' => $nik]);

        return EventParticipant::create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta', 'nik' => $nik, 'jabatan_label' => 'Analis',
        ]);
    }

    /** @param  list<string>  $rows */
    private function import(Event $event, array $rows)
    {
        $file = UploadedFile::fake()->createWithContent('peserta.csv', implode("\n", ['nama,nik,jabatan,sesi', ...$rows]));

        return $this->actingAs($this->admin)
            ->post(route('admin.events.participants.import', $event), ['file' => $file])
            ->assertRedirect(route('admin.events.participants', $event));
    }

    private function reportSays(array $report, string $text): bool
    {
        return collect($report['errors'])->contains(fn (array $error) => str_contains($error['message'], $text));
    }
}
