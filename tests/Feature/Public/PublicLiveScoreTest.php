<?php

namespace Tests\Feature\Public;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Livewire\Public\LiveScoreShow;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicLiveScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_is_accessible_without_login_and_lists_only_public_events(): void
    {
        $public = $this->makeEvent('Event Publik', publicLivescore: true);
        $private = $this->makeEvent('Event Privat', publicLivescore: false);

        // No actingAs — guest access.
        $this->get(route('public.livescore.index'))
            ->assertOk()
            ->assertSee('Event Publik')
            ->assertDontSee('Event Privat');
    }

    public function test_public_livescore_is_accessible_without_login(): void
    {
        $event = $this->makeEvent('Tryout Publik', publicLivescore: true);

        $this->get(route('public.livescore.show', $event))
            ->assertOk()
            ->assertSee('Tryout Publik');
    }

    public function test_public_url_uses_the_unique_code_not_the_id(): void
    {
        $event = $this->makeEvent('Tryout Publik', publicLivescore: true);

        $this->assertNotEmpty($event->public_code);
        $this->assertStringEndsWith('/livescore/'.$event->public_code, route('public.livescore.show', $event));

        // The old id-based URL must no longer resolve.
        $this->get('/livescore/'.$event->id)->assertNotFound();
    }

    public function test_every_new_event_gets_a_unique_public_code(): void
    {
        $a = $this->makeEvent('Event A', publicLivescore: true);
        $b = $this->makeEvent('Event B', publicLivescore: true);

        $this->assertNotSame($a->public_code, $b->public_code);
    }

    public function test_non_public_event_livescore_returns_404_even_to_guests(): void
    {
        $event = $this->makeEvent('Rahasia', publicLivescore: false);

        $this->get(route('public.livescore.show', $event))->assertNotFound();
    }

    public function test_ranking_is_ordered_by_score_descending(): void
    {
        $event = $this->makeEvent('Tryout', publicLivescore: true);
        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'RANK01',
            'status' => EventStatus::Active,
        ]);

        $this->makeSubmittedAttempt($event, $session, 'Budi', 120);
        $this->makeSubmittedAttempt($event, $session, 'Ani', 340);
        $this->makeSubmittedAttempt($event, $session, 'Citra', 210);

        $rows = Livewire::test(LiveScoreShow::class, ['event' => $event])->get('rows');

        $this->assertSame(['Ani', 'Citra', 'Budi'], array_column($rows, 'name'));
        $this->assertSame([1, 2, 3], array_column($rows, 'rank'));
    }

    private function makeEvent(string $name, bool $publicLivescore): Event
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Paket '.$name,
            'slug' => 'paket-'.str($name)->slug(),
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        return Event::query()->create([
            'name' => $name,
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'public_livescore' => $publicLivescore,
            'created_by' => $admin->id,
        ]);
    }

    private function makeSubmittedAttempt(Event $event, EventSession $session, string $name, int $score): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta, 'name' => $name]);

        ExamAttempt::query()->create([
            'exam_id' => $event->exam_id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $peserta->id,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
            'expires_at' => now()->addMinutes(70),
            'status' => ExamAttemptStatus::Submitted,
            'total_score' => $score,
        ]);
    }
}
