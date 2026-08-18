<?php

namespace Tests\Unit;

use App\Enums\SkdTarget;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkdTargetPassingGradesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpns_passing_grades_match_official_thresholds(): void
    {
        $grades = exam_passing_grades(SkdTarget::Cpns);

        $this->assertSame(65, $grades['twk']);
        $this->assertSame(80, $grades['tiu']);
        $this->assertSame(166, $grades['tkp']);
        $this->assertSame(311, $grades['total']);
    }

    public function test_sekolah_kedinasan_passing_grades_match_official_thresholds(): void
    {
        $grades = exam_passing_grades(SkdTarget::SekolahKedinasan);

        $this->assertSame(65, $grades['twk']);
        $this->assertSame(80, $grades['tiu']);
        $this->assertSame(156, $grades['tkp']);
        $this->assertSame(301, $grades['total']);
    }

    public function test_minimum_passing_grades_use_lowest_thresholds(): void
    {
        $minimum = SkdTarget::minimumPassingGrades();

        $this->assertSame(156, $minimum['tkp']);
        $this->assertSame(301, $minimum['total']);
    }

    public function test_passing_grades_can_be_overridden_via_settings(): void
    {
        Setting::setValue(
            'exam_passing_grades',
            json_encode([
                'cpns' => ['twk' => 70, 'tiu' => 85, 'tkp' => 170, 'total' => 320],
                'sekolah_kedinasan' => ['twk' => 65, 'tiu' => 80, 'tkp' => 160, 'total' => 305],
            ]),
            'exam',
            'json',
        );

        $cpns = exam_passing_grades(SkdTarget::Cpns);
        $kedinasan = exam_passing_grades(SkdTarget::SekolahKedinasan);

        $this->assertSame(70, $cpns['twk']);
        $this->assertSame(320, $cpns['total']);
        $this->assertSame(160, $kedinasan['tkp']);
        $this->assertSame(305, $kedinasan['total']);
    }

    public function test_same_scores_can_pass_kedinasan_but_not_cpns_on_tkp_threshold(): void
    {
        $this->assertFalse(exam_attempt_passes(65, 80, 160, 305, SkdTarget::Cpns));
        $this->assertTrue(exam_attempt_passes(65, 80, 160, 305, SkdTarget::SekolahKedinasan));
    }

    public function test_default_passing_grades_use_cpns_profile(): void
    {
        $this->assertSame(
            exam_passing_grades(SkdTarget::Cpns),
            exam_passing_grades(),
        );
    }
}
