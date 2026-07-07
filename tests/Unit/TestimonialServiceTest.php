<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\TestimonialFeatureTag;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_prompt_user_when_no_testimonial_and_has_completed_attempt(): void
    {
        $user = $this->createUserWithSubmittedAttempt();

        $this->assertTrue(app(TestimonialService::class)->shouldPromptUser($user));
    }

    public function test_should_not_prompt_user_without_completed_attempt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->assertFalse(app(TestimonialService::class)->shouldPromptUser($user));
    }

    public function test_should_not_prompt_user_who_already_submitted_testimonial(): void
    {
        $user = $this->createUserWithSubmittedAttempt();

        app(TestimonialService::class)->submit($user, [
            'target_instansi' => 'Calon Auditor — Pemprov Jatim',
            'story' => 'Cerita panjang tentang perjalanan belajar saya yang sangat membantu sekali.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::AudioMode->value],
            'rating' => 5,
            'is_anonymous' => false,
        ]);

        $this->assertFalse(app(TestimonialService::class)->shouldPromptUser($user));
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
