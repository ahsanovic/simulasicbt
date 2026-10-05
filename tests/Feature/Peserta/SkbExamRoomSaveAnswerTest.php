<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\UserRole;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
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

class SkbExamRoomSaveAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_an_option_does_not_save_or_count_as_answered(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->answerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->set('selectedOptionId', $optionId);

        $component->assertSet('selectedOptionId', $optionId);
        $this->assertNull($first->fresh()->selected_option_id);
        $this->assertNull($component->get('answerStates')[0]['selected_option_id']);
        $this->assertSame(0, $component->instance()->answeredCount);
    }

    public function test_simpan_dan_lanjutkan_saves_the_selected_option(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->answerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->set('selectedOptionId', $optionId)
            ->call('next');

        $component->assertSet('currentIndex', 1);
        $this->assertSame($optionId, $first->fresh()->selected_option_id);
        $this->assertSame($optionId, $component->get('answerStates')[0]['selected_option_id']);
        $this->assertSame(1, $component->instance()->answeredCount);
    }

    public function test_navigating_away_discards_an_unsaved_pick(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->answerAt($attempt, 1);
        $optionId = $first->question->options->first()->id;

        $component = Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->set('selectedOptionId', $optionId)
            ->call('goToQuestion', 1)
            ->call('goToQuestion', 0);

        $component->assertSet('selectedOptionId', null);
        $this->assertNull($first->fresh()->selected_option_id);
    }

    public function test_last_question_is_saved_with_simpan_jawaban(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $last = $this->answerAt($attempt, 3);
        $optionId = $last->question->options->first()->id;

        Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->call('goToQuestion', 2)
            ->assertSee('Simpan Jawaban')
            ->set('selectedOptionId', $optionId)
            ->call('saveAnswer');

        $this->assertSame($optionId, $last->fresh()->selected_option_id);
    }

    public function test_selesai_ujian_saves_the_unsaved_pick_on_the_last_question(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $component = Livewire::actingAs($user)->test(SkbExamRoom::class);

        // Answer every question; the last one is picked but "Simpan Jawaban"
        // is never clicked before "Selesai Ujian".
        foreach ([1, 2, 3] as $sortOrder) {
            $component->set('selectedOptionId', $this->answerAt($attempt, $sortOrder)->question->correctOption()->id);

            if ($sortOrder < 3) {
                $component->call('next');
            }
        }

        $component->call('submitExam');

        $attempt->refresh();
        $this->assertSame(ExamAttemptStatus::Submitted, $attempt->status);
        $this->assertSame(3, $attempt->correct_count);
        $this->assertNotNull($this->answerAt($attempt, 3)->selected_option_id);
    }

    public function test_last_question_shows_saved_state_after_simpan_jawaban(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $optionId = $this->answerAt($attempt, 3)->question->options->first()->id;

        // The "Jawaban Tersimpan" badge and the "Simpan Jawaban" button are both
        // in the page; the browser shows one of them by comparing the pick on
        // screen with savedOptionId. The server renders the right one visible.
        $savedBadgeHidden = '/style="display: none;?"\s+class="inline-flex[^"]*emerald[^"]*">\s*<svg[^>]*>.*?<\/svg>\s*Jawaban Tersimpan/s';

        $component = Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            ->call('goToQuestion', 2)
            ->set('selectedOptionId', $optionId)
            ->assertSet('savedOptionId', null);
        $this->assertMatchesRegularExpression($savedBadgeHidden, $component->html());

        $component->call('saveAnswer')->assertSet('savedOptionId', $optionId);
        $this->assertDoesNotMatchRegularExpression($savedBadgeHidden, $component->html());
    }

    public function test_option_from_another_question_is_rejected(): void
    {
        [$user, $attempt] = $this->startSkbAttempt();
        $first = $this->answerAt($attempt, 1);
        $foreignOptionId = $this->answerAt($attempt, 2)->question->options->first()->id;

        Livewire::actingAs($user)
            ->test(SkbExamRoom::class)
            // The pick comes from the browser; saving ignores an option that
            // does not belong to the question on screen.
            ->set('selectedOptionId', $foreignOptionId)
            ->call('next');

        $this->assertNull($first->fresh()->selected_option_id);
    }

    public function test_service_refuses_to_save_after_attempt_is_submitted(): void
    {
        [, $attempt] = $this->startSkbAttempt();
        $first = $this->answerAt($attempt, 1);
        $service = app(SkbExamService::class);

        $service->submitAttempt($attempt);

        $saved = $service->saveAnswer($attempt->fresh(), $first->skb_question_id, $first->question->options->first()->id);

        $this->assertFalse($saved);
        $this->assertNull($first->fresh()->selected_option_id);
    }

    /**
     * @return array{0: User, 1: SkbExamAttempt}
     */
    private function startSkbAttempt(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis SKB Room',
            'slug' => 'analis-skb-room-'.uniqid(),
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
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
            'name' => 'Mode Ujian SKB Room Test',
            'exam_id' => null,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
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
            'name' => 'Peserta SKB',
            'nik' => '3201010101019999',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $attempt = app(SkbExamService::class)->startAttempt($event, $participant);

        $this->assertSame(ExamAttemptStatus::InProgress, $attempt->status);

        return [$user, $attempt];
    }

    private function answerAt(SkbExamAttempt $attempt, int $sortOrder): SkbExamAnswer
    {
        return SkbExamAnswer::query()
            ->where('skb_exam_attempt_id', $attempt->id)
            ->where('sort_order', $sortOrder)
            ->with('question.options')
            ->firstOrFail();
    }
}
