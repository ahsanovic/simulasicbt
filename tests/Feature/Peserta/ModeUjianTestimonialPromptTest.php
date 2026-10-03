<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Pengingat testimoni" popup must never appear inside Mode Ujian — it is
 * an official exam, not the practice app.
 */
class ModeUjianTestimonialPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_testimonial_prompt_is_hidden_on_mode_ujian_skd_result(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'SKD Mode Ujian',
            'slug' => 'skd-mode-ujian',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Mode Ujian Testimoni',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skd,
        ]);

        // A submitted attempt is what makes the user eligible for the prompt.
        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'display_name' => 'Peserta Mode Ujian',
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinutes(20),
            'status' => ExamAttemptStatus::Submitted,
        ]);

        $this->actingAs($user)
            ->get(route('peserta.mode-ujian.skd-result', $attempt))
            ->assertOk()
            ->assertSee('Hasil SKD')
            ->assertDontSee('Pengingat testimoni');
    }
}
