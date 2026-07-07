<?php

namespace Tests\Unit;

use App\Enums\DevotionBadge;
use App\Services\GamificationService;
use Tests\TestCase;

class DevotionBadgeTest extends TestCase
{
    public function test_resolves_pejuang_akuntabel_for_low_xp(): void
    {
        $badge = DevotionBadge::fromXp(0);

        $this->assertSame(DevotionBadge::PejuangAkuntabel, $badge);
        $this->assertSame('Pejuang Akuntabel', $badge->label());
        $this->assertStringContainsString('emerald', $badge->badgeClasses());
    }

    public function test_resolves_rekan_kompeten_for_mid_low_xp(): void
    {
        $badge = DevotionBadge::fromXp(1500);

        $this->assertSame(DevotionBadge::RekanKompeten, $badge);
        $this->assertStringContainsString('indigo', $badge->badgeClasses());
    }

    public function test_resolves_abdi_harmonis_for_mid_xp(): void
    {
        $badge = DevotionBadge::fromXp(4000);

        $this->assertSame(DevotionBadge::AbdiHarmonis, $badge);
    }

    public function test_resolves_penggerak_adaptif_for_high_mid_xp(): void
    {
        $badge = DevotionBadge::fromXp(6500);

        $this->assertSame(DevotionBadge::PenggerakAdaptif, $badge);
    }

    public function test_resolves_teladan_loyal_for_top_tier_xp(): void
    {
        $badge = DevotionBadge::fromXp(8000);

        $this->assertSame(DevotionBadge::TeladanLoyal, $badge);
        $this->assertStringContainsString('amber', $badge->badgeClasses());
    }

    public function test_helper_returns_badge_payload(): void
    {
        $payload = devotion_badge_for_xp(1200);

        $this->assertSame('rekan_kompeten', $payload['value']);
        $this->assertSame('Rekan Kompeten', $payload['label']);
        $this->assertSame(1001, $payload['min_xp']);
        $this->assertSame('indigo', $payload['tooltip_theme']);
        $this->assertNotSame('', $payload['description']);
        $this->assertNotSame('', $payload['classes']);
    }

    public function test_devotion_progress_calculates_next_tier(): void
    {
        $progress = app(GamificationService::class)->devotionProgress(500);

        $this->assertSame('pejuang_akuntabel', $progress['current_badge']['value']);
        $this->assertSame('rekan_kompeten', $progress['next_badge']['value']);
        $this->assertSame(501, $progress['xp_to_next']);
        $this->assertFalse($progress['is_max_tier']);
        $this->assertCount(5, $progress['tiers']);
    }

    public function test_devotion_progress_marks_max_tier(): void
    {
        $progress = app(GamificationService::class)->devotionProgress(9000);

        $this->assertSame('teladan_loyal', $progress['current_badge']['value']);
        $this->assertNull($progress['next_badge']);
        $this->assertTrue($progress['is_max_tier']);
        $this->assertSame(100, $progress['progress_percent']);
    }
}
