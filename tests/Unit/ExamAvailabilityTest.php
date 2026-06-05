<?php

namespace Tests\Unit;

use App\Enums\ExamStatus;
use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_exam_is_available_after_start_time_in_app_timezone(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);

        Carbon::setTestNow(Carbon::parse('2026-06-05 12:00:00', 'Asia/Jakarta'));

        $exam = Exam::query()->create([
            'title' => 'Simulasi WIB',
            'slug' => 'simulasi-wib',
            'duration_minutes' => 100,
            'starts_at' => '2026-06-05 10:00:00',
            'ends_at' => '2026-06-30 12:00:00',
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
        ]);

        $this->assertTrue($exam->fresh()->isAvailable());
    }

    public function test_published_exam_is_not_available_before_start_time_in_app_timezone(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);

        Carbon::setTestNow(Carbon::parse('2026-06-05 09:30:00', 'Asia/Jakarta'));

        $exam = Exam::query()->create([
            'title' => 'Simulasi WIB',
            'slug' => 'simulasi-wib-belum',
            'duration_minutes' => 100,
            'starts_at' => '2026-06-05 10:00:00',
            'ends_at' => '2026-06-30 12:00:00',
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
        ]);

        $this->assertFalse($exam->fresh()->isAvailable());
    }
}
