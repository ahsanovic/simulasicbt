<?php

namespace Tests\Unit;

use App\Enums\SubjectCode;
use PHPUnit\Framework\TestCase;

class SubjectCodeScoringTest extends TestCase
{
    public function test_twk_and_tiu_award_five_points_when_correct(): void
    {
        $this->assertSame(5, SubjectCode::Twk->pointsFromSelectedOption(null, true));
        $this->assertSame(0, SubjectCode::Twk->pointsFromSelectedOption(null, false));
        $this->assertSame(5, SubjectCode::Tiu->pointsFromSelectedOption(null, true));
    }

    public function test_tkp_uses_weight_between_one_and_five(): void
    {
        $this->assertSame(5, SubjectCode::Tkp->pointsFromSelectedOption(5, false));
        $this->assertSame(1, SubjectCode::Tkp->pointsFromSelectedOption(1, false));
        $this->assertSame(3, SubjectCode::Tkp->pointsFromSelectedOption(3, false));
        $this->assertSame(1, SubjectCode::Tkp->pointsFromSelectedOption(0, false));
        $this->assertSame(5, SubjectCode::Tkp->pointsFromSelectedOption(99, false));
    }
}
