<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\Events\Index as EventsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModeUjianAutoSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_mode_ujian_event_auto_creates_sessions_with_unique_generated_pins(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $exam = \App\Models\Exam::create(['title' => 'Auto Session Exam', 'slug' => 'auto-session-exam', 'duration_minutes' => 100, 'status' => 'published']);

        Livewire::actingAs($admin)
            ->test(EventsIndex::class)
            ->call('openCreateModal')
            ->set('name', 'Auto Session Event')
            ->set('is_mode_ujian', true)
            ->set('exam_id', $exam->id)
            ->set('exam_mode', 'both')
            ->set('sessionCount', 3)
            ->set('skb_question_count', 40)
            ->set('skb_correct_score', 5)
            ->set('skb_duration_minutes', 90)
            ->call('save')
            ->assertHasNoErrors();

        $event = \App\Models\Event::query()->where('name', 'Auto Session Event')->firstOrFail();
        $sessions = $event->sessions()->orderBy('id')->get();

        $this->assertCount(3, $sessions);
        $this->assertSame(['Sesi 1', 'Sesi 2', 'Sesi 3'], $sessions->pluck('name')->all());

        // Every session got its own PIN for both phases, all non-empty.
        foreach ($sessions as $session) {
            $this->assertNotNull($session->skd_pin);
            $this->assertNotNull($session->skb_pin);
            $this->assertMatchesRegularExpression('/^\d{4}$/', $session->skd_pin);
            $this->assertMatchesRegularExpression('/^\d{4}$/', $session->skb_pin);
        }

        // No two sessions share the same PIN.
        $this->assertCount(3, $sessions->pluck('skd_pin')->unique());
        $this->assertCount(3, $sessions->pluck('skb_pin')->unique());
    }

    public function test_skb_only_event_sessions_only_get_a_skb_pin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(EventsIndex::class)
            ->call('openCreateModal')
            ->set('name', 'SKB Only Auto Session')
            ->set('is_mode_ujian', true)
            ->set('exam_mode', 'skb')
            ->set('sessionCount', 2)
            ->set('skb_question_count', 40)
            ->set('skb_correct_score', 5)
            ->set('skb_duration_minutes', 90)
            ->call('save')
            ->assertHasNoErrors();

        $event = \App\Models\Event::query()->where('name', 'SKB Only Auto Session')->firstOrFail();

        foreach ($event->sessions as $session) {
            $this->assertNull($session->skd_pin);
            $this->assertNotNull($session->skb_pin);
        }
    }
}
