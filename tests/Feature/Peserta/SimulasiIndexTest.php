<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Peserta\SimulasiIndex;
use App\Models\Exam;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SimulasiIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_simulasi_page(): void
    {
        $this->get(route('peserta.simulasi.index'))
            ->assertRedirect(route('login'));
    }

    public function test_peserta_can_view_simulasi_page_with_available_exams(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();

        Livewire::actingAs($user)
            ->test(SimulasiIndex::class)
            ->assertSee('Simulasi SKD Penuh')
            ->assertSee('Ujian Tersedia')
            ->assertSee($exam->title)
            ->assertSee('Mulai Simulasi');
    }

    public function test_simulasi_page_can_start_exam_with_stress_test_modal(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $exam = $this->createPublishedExam();

        Livewire::actingAs($user)
            ->test(SimulasiIndex::class)
            ->call('startExam', $exam->id)
            ->assertSet('stressTestExamId', $exam->id)
            ->assertSee('Aktifkan Mode Stress-Test');
    }

    private function createPublishedExam(): Exam
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return Exam::query()->create([
            'title' => 'Simulasi CPNS Lengkap',
            'slug' => 'simulasi-cpns-'.uniqid(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'pin' => null,
            'settings' => [
                'difficulty' => 'all',
                'question_counts' => ExamQuestionGeneratorService::COUNTS_BY_SUBJECT,
                'total_questions' => ExamQuestionGeneratorService::TOTAL_QUESTIONS,
            ],
            'created_by' => $admin->id,
        ]);
    }
}
