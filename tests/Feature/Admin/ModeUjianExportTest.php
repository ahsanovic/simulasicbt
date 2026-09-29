<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Exports\EventParticipantsExport;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\SkbExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

class ModeUjianExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mode_ujian_export_falls_back_to_nik_when_nip_blank(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skd);

        $user = User::factory()->create(['role' => UserRole::Peserta, 'nip' => null, 'nik' => '3201010101010001']);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta Tanpa NIP',
            'nik' => '3201010101010001',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $rows = (new EventParticipantsExport($event))->collection();

        $this->assertCount(1, $rows);
        $this->assertSame('3201010101010001', $rows->first()[2]);
    }

    public function test_mode_ujian_export_writes_nik_as_text_preserving_trailing_zeros(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skd);

        // Excel auto-detects numeric-looking strings as floats, which drops
        // this trailing "001" to "000" unless the cell is forced to text.
        $user = User::factory()->create(['role' => UserRole::Peserta, 'nip' => null, 'nik' => '3201010101010001']);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta NIK',
            'nik' => '3201010101010001',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $path = 'test-exports/'.uniqid('nik-precision-').'.xlsx';
        Excel::store(new EventParticipantsExport($event), $path, 'local');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(storage_path('app/private/'.$path));
        $sheet = $spreadsheet->getActiveSheet();
        $cell = $sheet->getCell('C2');

        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame('3201010101010001', $cell->getValue());

        @unlink(storage_path('app/private/'.$path));
    }

    public function test_mode_ujian_export_writes_zero_score_as_number_not_blank_cell(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        // PhpSpreadsheet's default row-writer compares each value against
        // null with loose `!=`, and 0 == null in PHP — without
        // WithStrictNullComparison on the export class, every 0 score is
        // silently dropped and the cell comes back empty on reload.
        $user = User::factory()->create(['role' => UserRole::Peserta, 'nik' => '3201010101010005']);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Belum Mulai',
            'nik' => '3201010101010005',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $path = 'test-exports/'.uniqid('zero-score-').'.xlsx';
        Excel::store(new EventParticipantsExport($event), $path, 'local');

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(storage_path('app/private/'.$path));
        $sheet = $spreadsheet->getActiveSheet();

        // No, Nama, NIP/NIK, Jabatan, Sesi, then SKB - Benar is column F.
        $this->assertSame(0, $sheet->getCell('F2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('F2')->getDataType());
        $this->assertSame(0, $sheet->getCell('G2')->getValue());
        $this->assertSame(0, $sheet->getCell('H2')->getValue());

        @unlink(storage_path('app/private/'.$path));
    }

    public function test_mode_ujian_export_prefers_nip_over_nik_when_present(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skd);

        $user = User::factory()->create(['role' => UserRole::Peserta, 'nip' => '198001012020121001', 'nik' => '3201010101010002']);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta Dengan NIP',
            'nik' => '3201010101010002',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $rows = (new EventParticipantsExport($event))->collection();

        $this->assertSame('198001012020121001', $rows->first()[2]);
    }

    public function test_mode_ujian_export_includes_skb_score_columns(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Both);

        $user = User::factory()->create(['role' => UserRole::Peserta, 'nik' => '3201010101010003']);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta SKB',
            'nik' => '3201010101010003',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        SkbExamAttempt::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'event_participant_id' => $participant->id,
            'user_id' => $user->id,
            'jabatan_skb_id' => $jabatan->id,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(50),
            'status' => ExamAttemptStatus::Submitted,
            'correct_score' => 5,
            'correct_count' => 8,
            'total_score' => 40,
        ]);

        $headings = (new EventParticipantsExport($event))->headings();
        $this->assertContains('SKB - Benar', $headings);
        $this->assertContains('SKB - Total Skor', $headings);

        $rows = (new EventParticipantsExport($event))->collection();
        $row = $rows->first();

        // No, Nama, NIP/NIK, Jabatan, Sesi, then SKD block (9 cols), then SKB block.
        $skbStart = 5 + 9;
        $this->assertSame(8, $row[$skbStart]); // SKB - Benar
        $this->assertSame(40, $row[$skbStart + 2]); // SKB - Total Skor
        $this->assertSame('Selesai', $row[$skbStart + 3]); // SKB - Status
    }

    public function test_mode_ujian_export_marks_participants_who_have_not_started(): void
    {
        [$event, $session, $jabatan] = $this->makeModeUjianEvent(EventExamMode::Skb);

        $user = User::factory()->create(['role' => UserRole::Peserta, 'nik' => '3201010101010004']);
        EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Belum Mulai',
            'nik' => '3201010101010004',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $rows = (new EventParticipantsExport($event))->collection();
        $row = $rows->first();

        // No, Nama, NIP/NIK, Jabatan, Sesi, then SKB block only (SKB-only event).
        // Unfilled numeric columns write 0, never a blank/dash placeholder.
        $this->assertSame(0, $row[5]); // SKB - Benar
        $this->assertSame(0, $row[6]); // SKB - Total Soal
        $this->assertSame(0, $row[7]); // SKB - Total Skor
        $this->assertSame('Belum Mulai', $row[8]); // SKB - Status
    }

    public function test_legacy_offline_event_export_is_unaffected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = Exam::query()->create([
            'title' => 'Simulasi Legacy',
            'slug' => 'simulasi-legacy',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Legacy',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'LEGACY1',
            'status' => EventStatus::Active,
        ]);

        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'nip' => null]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $peserta->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(80),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 50,
            'score_tiu' => 60,
            'score_tkp' => 40,
            'total_score' => 150,
        ]);

        $headings = (new EventParticipantsExport($event))->headings();
        $this->assertSame(['No', 'Nama', 'NIP', 'Instansi', 'Sesi', 'Dikerjakan', 'Total Soal', 'Skor TWK', 'Skor TIU', 'Skor TKP', 'Total Skor', 'Status', 'Mulai', 'Selesai'], $headings);

        $rows = (new EventParticipantsExport($event))->collection();
        // Legacy NIP column keeps '' fallback — no NIK column exists on this model.
        $this->assertSame('', $rows->first()[2]);
    }

    /**
     * @return array{0: Event, 1: EventSession, 2: JabatanSkb}
     */
    private function makeModeUjianEvent(EventExamMode $mode): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'name' => 'Mode Ujian Export Test',
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => $mode,
            'skb_question_count' => 10,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skd_pin' => $mode->includesSkd() ? '1234' : null,
            'skb_pin' => $mode->includesSkb() ? '5678' : null,
            'status' => EventStatus::Active,
        ]);

        return [$event, $session, $jabatan];
    }
}
