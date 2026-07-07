<?php

namespace Tests\Feature\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_testimonial_prompt_for_eligible_user(): void
    {
        $user = $this->createUserWithSubmittedAttempt();

        $this->actingAs($user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertSee('Bagikan cerita Anda!', false)
            ->assertSee('Tulis Sekarang', false);
    }

    public function test_dashboard_hides_testimonial_prompt_without_completed_attempt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.dashboard'))
            ->assertOk()
            ->assertDontSee('Bagikan cerita Anda!', false);
    }

    public function test_testimonials_page_hides_testimonial_prompt(): void
    {
        $user = $this->createUserWithSubmittedAttempt();

        $this->actingAs($user)
            ->get(route('peserta.testimonials.index'))
            ->assertOk()
            ->assertDontSee('Bagikan cerita Anda!', false);
    }

    public function test_open_form_query_parameter_opens_testimonial_form(): void
    {
        $user = $this->createUserWithSubmittedAttempt();

        $this->actingAs($user)
            ->get(route('peserta.testimonials.index', ['open' => 'form']))
            ->assertOk()
            ->assertSee('Tulis Testimoni Anda', false);
    }

    private function createUserWithSubmittedAttempt(): User
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi SKD',
            'slug' => 'simulasi-skd',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 5,
            'score_tiu' => 0,
            'score_tkp' => 5,
            'total_score' => 10,
        ]);

        return $user;
    }
}
