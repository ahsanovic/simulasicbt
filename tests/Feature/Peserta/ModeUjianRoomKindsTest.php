<?php

namespace Tests\Feature\Peserta;

use App\Services\ModeUjianRoom\DeviceToken;
use App\Services\ModeUjianRoom\RoomKinds;
use App\Services\ModeUjianRoom\SkbRoom;
use App\Services\ModeUjianRoom\SkdRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * The browser exam room (Mode Ujian) finds the participant's attempt per kind
 * (SKD / SKB) and only accepts answers from the device that opened it last.
 */
class ModeUjianRoomKindsTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    public function test_skd_room_finds_the_in_progress_mode_ujian_attempt(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $kind = RoomKinds::get('skd');

        $this->assertInstanceOf(SkdRoom::class, $kind);
        $this->assertSame($ctx['attempt']->id, $kind->activeAttempt($ctx['user']->id)?->id);
        $this->assertSame($ctx['attempt']->id, $kind->ownedAttempt($ctx['user']->id, $ctx['attempt']->id)?->id);
        $this->assertNull($kind->ownedAttempt($ctx['user']->id + 1, $ctx['attempt']->id));
    }

    public function test_skd_room_ignores_simulasi_attempts(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);

        $this->assertNull(RoomKinds::get('skd')->activeAttempt($ctx['user']->id));
    }

    public function test_skb_room_finds_the_in_progress_attempt(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $kind = RoomKinds::get('skb');

        $this->assertInstanceOf(SkbRoom::class, $kind);
        $this->assertSame($attempt->id, $kind->activeAttempt($user->id)?->id);
    }

    public function test_unknown_kind_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        RoomKinds::get('lainnya');
    }

    public function test_a_new_device_token_replaces_the_previous_one(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);

        $first = DeviceToken::issue($ctx['attempt']);
        $second = DeviceToken::issue($ctx['attempt']);
        $stored = $ctx['attempt']->fresh();

        $this->assertSame(40, strlen($second));
        $this->assertNotSame($first, $second);
        $this->assertTrue(DeviceToken::matches($stored, $second));
        $this->assertFalse(DeviceToken::matches($stored, $first));
        $this->assertFalse(DeviceToken::matches($stored, null));
    }

    public function test_the_switch_defaults_to_off(): void
    {
        $this->assertFalse(config('exam.client_room'));
    }
}
