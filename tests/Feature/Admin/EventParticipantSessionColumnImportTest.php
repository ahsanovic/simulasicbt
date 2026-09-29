<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\JabatanSkb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EventParticipantSessionColumnImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = 'nama,nik,jabatan,sesi';

    public function test_one_file_assigns_participants_to_different_sessions_by_the_sesi_column(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        JabatanSkb::create(['name' => 'Perawat', 'slug' => 'perawat', 'is_active' => true]);

        $event = Event::create([
            'name' => 'Sesi Column Test', 'code' => Event::generateUniqueCode(),
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 5, 'skb_correct_score' => 5, 'skb_duration_minutes' => 30,
            'status' => 'active',
        ]);

        $sessionPagi = EventSession::create(['event_id' => $event->id, 'name' => 'Sesi Pagi', 'code' => EventSession::generateUniqueCode(), 'skb_pin' => '1111', 'status' => 'active']);
        $sessionSiang = EventSession::create(['event_id' => $event->id, 'name' => 'Sesi Siang', 'code' => EventSession::generateUniqueCode(), 'skb_pin' => '2222', 'status' => 'active']);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Peserta Pagi,1111111111111111,Perawat,Sesi Pagi',
            'Peserta Siang,2222222222222222,Perawat,Sesi Siang',
        ]);

        $file = UploadedFile::fake()->createWithContent('peserta.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.events.participants.import', $event), ['file' => $file]);

        $response->assertRedirect(route('admin.events.participants', $event));
        $response->assertSessionHas('success');

        $participantPagi = $event->participants()->where('nik', '1111111111111111')->firstOrFail();
        $participantSiang = $event->participants()->where('nik', '2222222222222222')->firstOrFail();

        $this->assertSame($sessionPagi->id, $participantPagi->event_session_id);
        $this->assertSame($sessionSiang->id, $participantSiang->event_session_id);
    }

    public function test_unrecognized_session_name_is_rejected_with_a_clear_error(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        JabatanSkb::create(['name' => 'Perawat', 'slug' => 'perawat', 'is_active' => true]);

        $event = Event::create([
            'name' => 'Sesi Column Reject Test', 'code' => Event::generateUniqueCode(),
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 5, 'skb_correct_score' => 5, 'skb_duration_minutes' => 30,
            'status' => 'active',
        ]);

        EventSession::create(['event_id' => $event->id, 'name' => 'Sesi Pagi', 'code' => EventSession::generateUniqueCode(), 'skb_pin' => '1111', 'status' => 'active']);

        $csv = implode("\n", [
            self::CSV_HEADER,
            'Peserta Salah,3333333333333333,Perawat,Sesi Yang Tidak Ada',
        ]);

        $file = UploadedFile::fake()->createWithContent('peserta.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.events.participants.import', $event), ['file' => $file]);

        $response->assertRedirect(route('admin.events.participants', $event));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('event_participants', ['nik' => '3333333333333333']);
    }

    public function test_import_template_includes_real_session_names_as_example(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::create([
            'name' => 'Template Session Test', 'code' => Event::generateUniqueCode(),
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 5, 'skb_correct_score' => 5, 'skb_duration_minutes' => 30,
            'status' => 'active',
        ]);

        EventSession::create(['event_id' => $event->id, 'name' => 'Sesi Khusus', 'code' => EventSession::generateUniqueCode(), 'skb_pin' => '1111', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.events.participants.import-template', $event));

        $response->assertOk();
        $this->assertStringContainsString(
            'template-import-peserta-event.xlsx',
            $response->headers->get('content-disposition'),
        );
    }
}
