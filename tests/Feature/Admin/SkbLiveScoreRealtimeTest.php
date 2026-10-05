<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Livewire\Public\LiveScoreShow;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\JabatanSkb;
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\User;
use App\Services\SkbExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SkbLiveScoreRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_board_scores_in_progress_skb_attempt_from_saved_answers(): void
    {
        [$admin, $event, $session, $attempt] = $this->startSkbAttempt();

        // 2 benar + 1 salah tersimpan, attempt masih berjalan.
        $this->answer($attempt, 1, correct: true);
        $this->answer($attempt, 2, correct: true);
        $this->answer($attempt, 3, correct: false);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->instance()
            ->allRows();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['in_progress']);
        $this->assertSame(3, $rows[0]['answered']);
        $this->assertSame(2, $rows[0]['benar']);
        $this->assertSame(10, $rows[0]['score']); // 2 x skb_correct_score (5)
    }

    public function test_public_board_scores_in_progress_skb_attempt_from_saved_answers(): void
    {
        [, $event, , $attempt] = $this->startSkbAttempt();

        $this->answer($attempt, 1, correct: true);

        $rows = Livewire::test(LiveScoreShow::class, ['event' => $event])
            ->instance()
            ->rows();

        $this->assertSame(1, $rows[0]['benar']);
        $this->assertSame(5, $rows[0]['score']);
    }

    public function test_submitted_attempt_keeps_stored_score(): void
    {
        [$admin, $event, $session, $attempt] = $this->startSkbAttempt();

        $this->answer($attempt, 1, correct: true);
        $this->answer($attempt, 2, correct: true);
        app(SkbExamService::class)->submitAttempt($attempt);

        $rows = Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->instance()
            ->allRows();

        $this->assertFalse($rows[0]['in_progress']);
        $this->assertSame(2, $rows[0]['benar']);
        $this->assertSame(10, $rows[0]['score']);
    }

    public function test_boards_poll_every_10_seconds(): void
    {
        [$admin, $event, $session] = $this->startSkbAttempt();

        Livewire::actingAs($admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session])
            ->assertSeeHtml('wire:poll.30s="pollBoard"');

        Livewire::test(LiveScoreShow::class, ['event' => $event])
            ->assertSeeHtml('wire:poll.30s="refreshBoard"');
    }

    private function answer(SkbExamAttempt $attempt, int $sortOrder, bool $correct): void
    {
        $answer = SkbExamAnswer::query()
            ->where('skb_exam_attempt_id', $attempt->id)
            ->where('sort_order', $sortOrder)
            ->with('question.options')
            ->firstOrFail();

        $option = $answer->question->options->firstWhere('is_correct', $correct);

        app(SkbExamService::class)->saveAnswer($attempt, $answer->skb_question_id, $option->id);
    }

    /**
     * @return array{0: User, 1: Event, 2: EventSession, 3: SkbExamAttempt}
     */
    private function startSkbAttempt(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Live SKB',
            'slug' => 'analis-live-skb-'.uniqid(),
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $question = SkbQuestion::query()->create([
                'jabatan_skb_id' => $jabatan->id,
                'content' => "<p>Soal {$i}</p>",
                'difficulty' => 'medium',
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $order => $label) {
                $question->options()->create([
                    'label' => $label,
                    'content_type' => QuestionOptionContentType::Text,
                    'content' => "Opsi {$label}",
                    'is_correct' => $label === 'A',
                    'sort_order' => $order + 1,
                ]);
            }
        }

        $event = Event::query()->create([
            'name' => 'Live SKB Test',
            'exam_id' => null,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 4,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
            'public_livescore' => true,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skb_pin' => '5678',
            'status' => EventStatus::Active,
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta Live SKB',
            'nik' => '3201010101018888',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $attempt = app(SkbExamService::class)->startAttempt($event, $participant);

        return [$admin, $event, $session, $attempt];
    }
}
