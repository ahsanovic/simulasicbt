<?php

namespace Tests\Feature\Peserta;

use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\ModeUjian\ClientExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * Mode Ujian exam rooms show who is sitting the exam (name and full NIK) in
 * the header, so the proctor can check it against the identity card.
 */
class ModeUjianParticipantIdentityTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    private const NIK = '3201010101017777';

    public function test_the_skd_room_shows_the_confirmed_name_and_the_nik(): void
    {
        config(['exam.client_room' => false]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['user']->update(['nik' => self::NIK]);

        Livewire::actingAs($ctx['user']->fresh())->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSee('Peserta Deadline')
            ->assertSee('NIK '.self::NIK);
    }

    public function test_the_skb_room_shows_the_name_and_the_nik(): void
    {
        config(['exam.client_room' => false]);
        [$user] = $this->startSkbAttempt();
        $user->update(['name' => 'Peserta SKB Identitas', 'nik' => self::NIK]);

        Livewire::actingAs($user->fresh())->test(SkbExamRoom::class)
            ->assertSee('Peserta SKB Identitas')
            ->assertSee('NIK '.self::NIK);
    }

    public function test_the_browser_room_shows_the_name_and_the_nik(): void
    {
        config(['exam.client_room' => true]);
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['user']->update(['nik' => self::NIK]);

        Livewire::actingAs($ctx['user']->fresh())->test(ClientExamRoom::class, ['kind' => 'skd'])
            ->assertSee('Peserta Deadline')
            ->assertSee('NIK '.self::NIK);
    }

    public function test_a_simulasi_room_shows_no_nik(): void
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60);
        $ctx['user']->update(['nik' => self::NIK]);

        Livewire::actingAs($ctx['user']->fresh())->test(ExamRoom::class, ['exam' => $ctx['exam']])
            ->assertSee('Soal nomor 1?')
            ->assertDontSee(self::NIK);
    }
}
