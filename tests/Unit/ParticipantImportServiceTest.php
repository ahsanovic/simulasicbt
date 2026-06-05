<?php

namespace Tests\Unit;

use App\Services\ParticipantImportService;
use Tests\TestCase;

class ParticipantImportServiceTest extends TestCase
{
    public function test_background_threshold_is_fifty(): void
    {
        $this->assertSame(50, ParticipantImportService::BACKGROUND_ROW_THRESHOLD);
    }
}
