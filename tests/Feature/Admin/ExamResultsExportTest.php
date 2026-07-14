<?php

namespace Tests\Feature\Admin;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExportRequestStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Results\Index;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExamResultsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_exam_results_export(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$exam, $peserta] = $this->seedSubmittedAttempt('Budi Export');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('requestExport')
            ->assertHasNoErrors()
            ->assertSet('activeExportId', fn ($id) => $id !== null);

        $this->assertDatabaseHas('export_requests', [
            'user_id' => $admin->id,
            'type' => ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            'status' => ExportRequestStatus::Completed->value,
            'total_rows' => 1,
        ]);

        $exportRequest = ExportRequest::query()->first();
        $this->assertNotNull($exportRequest?->file_path);
        Storage::disk('local')->assertExists($exportRequest->file_path);
    }

    public function test_export_respects_date_range_filter(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$exam, $peserta] = $this->seedSubmittedAttempt('Budi Lama', now()->subDays(10));
        [$exam2, $peserta2] = $this->seedSubmittedAttempt('Budi Baru', now()->subDay());

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('dateFrom', now()->subDays(3)->toDateString())
            ->set('dateTo', now()->toDateString())
            ->call('requestExport')
            ->assertHasNoErrors();

        $exportRequest = ExportRequest::query()->first();
        $this->assertSame(1, $exportRequest?->total_rows);
    }

    public function test_admin_can_download_completed_export(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $path = 'exports/exam-results/1/hasil-ujian.csv';
        Storage::disk('local')->put($path, "No,Nama\n1,Test");

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $admin->id,
            'type' => ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            'status' => ExportRequestStatus::Completed,
            'filters' => [],
            'file_path' => $path,
            'file_name' => 'hasil-ujian.csv',
            'total_rows' => 1,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.results.exports.download', $exportRequest))
            ->assertOk()
            ->assertDownload('hasil-ujian.csv');
    }

    public function test_peserta_cannot_download_admin_export(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);
        $path = 'exports/exam-results/1/hasil-ujian.csv';
        Storage::disk('local')->put($path, "No,Nama\n1,Test");

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $admin->id,
            'type' => ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            'status' => ExportRequestStatus::Completed,
            'filters' => [],
            'file_path' => $path,
            'file_name' => 'hasil-ujian.csv',
            'total_rows' => 1,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($peserta)
            ->get(route('admin.results.exports.download', $exportRequest))
            ->assertForbidden();
    }

    public function test_request_export_fails_when_no_data_matches_filter(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('search', 'tidak-ada-nama-ini')
            ->call('requestExport')
            ->assertHasErrors(['export']);
    }

    public function test_cleanup_command_removes_expired_exports(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $path = 'exports/exam-results/9/hasil-ujian.csv';
        Storage::disk('local')->put($path, 'expired');

        ExportRequest::query()->create([
            'user_id' => $admin->id,
            'type' => ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            'status' => ExportRequestStatus::Completed,
            'filters' => [],
            'file_path' => $path,
            'file_name' => 'hasil-ujian.csv',
            'total_rows' => 1,
            'completed_at' => now()->subDays(3),
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('exports:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('export_requests', [
            'file_path' => $path,
        ]);
        Storage::disk('local')->assertMissing($path);
    }

    /**
     * @return array{0: Exam, 1: User}
     */
    private function seedSubmittedAttempt(string $name, ?Carbon $submittedAt = null): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $peserta = User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => $name,
        ]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-'.str()->slug($name),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $peserta->id,
            'started_at' => ($submittedAt ?? now())->copy()->subHour(),
            'submitted_at' => $submittedAt ?? now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        return [$exam, $peserta];
    }
}
