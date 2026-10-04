<?php

namespace Tests\Feature\Admin;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\UserRole;
use App\Livewire\Admin\Events\LiveScore;
use App\Livewire\Admin\JabatanSkb\SoalIndex;
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

/**
 * Admin changes to the SKB question bank must never lose or corrupt answers
 * of participants who already got those questions, nor break their exam.
 */
class SkbQuestionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private JabatanSkb $jabatan;

    public function test_question_used_in_an_exam_cannot_be_deleted(): void
    {
        [, $attempt] = $this->startAttempt();
        $question = $attempt->answers()->first()->question;

        Livewire::actingAs($this->admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $this->jabatan])
            ->call('delete', $question->id);

        $this->assertNotSoftDeleted($question);
    }

    public function test_unused_question_can_still_be_deleted(): void
    {
        $this->startAttempt();
        $unused = $this->makeQuestion('Tidak terpakai');

        Livewire::actingAs($this->admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $this->jabatan])
            ->call('delete', $unused->id);

        $this->assertSoftDeleted($unused);
    }

    public function test_exam_still_submits_and_livescore_loads_if_a_used_question_was_already_deleted(): void
    {
        // e.g. data deleted before this fix shipped
        [$user, $attempt, $event, $session] = $this->startAttempt();
        $answer = $attempt->answers()->first();
        $correctId = $answer->question->correctOption()->id;
        app(SkbExamService::class)->saveAnswer($attempt, $answer->skb_question_id, $correctId);
        $answer->question->delete();

        Livewire::actingAs($user)->test(SkbExamRoom::class)->assertOk();

        $submitted = app(SkbExamService::class)->submitAttempt($attempt->fresh());
        $this->assertSame(ExamAttemptStatus::Submitted, $submitted->status);
        $this->assertSame(1, $submitted->correct_count);

        Livewire::actingAs($this->admin)
            ->test(LiveScore::class, ['event' => $event, 'session' => $session, 'jenis' => 'skb'])
            ->assertOk();
    }

    public function test_editing_a_question_keeps_participant_answers(): void
    {
        [, $attempt] = $this->startAttempt();
        $answer = $attempt->answers()->first();
        $question = $answer->question;
        $optionId = $question->options()->orderBy('sort_order')->first()->id;
        app(SkbExamService::class)->saveAnswer($attempt, $question->id, $optionId);

        Livewire::actingAs($this->admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $this->jabatan])
            ->call('openEditModal', $question->id)
            ->set('options.0.content', 'Opsi A diperbaiki')
            ->call('save', '<p>Typo diperbaiki</p>')
            ->assertHasNoErrors();

        $this->assertSame($optionId, $answer->fresh()->selected_option_id, 'answer must survive the edit');
        $this->assertSame('Opsi A diperbaiki', $question->options()->find($optionId)->content);
        $this->assertStringContainsString('Typo diperbaiki', $question->fresh()->content);
    }

    public function test_editing_can_add_an_option_and_change_the_key(): void
    {
        $this->startAttempt();
        $question = $this->makeQuestion('Soal bebas');
        $oldIds = $question->options()->orderBy('sort_order')->pluck('id')->all();

        $component = Livewire::actingAs($this->admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $this->jabatan])
            ->call('openEditModal', $question->id);
        $options = $component->get('options');
        $options[] = ['label' => 'C', 'content_type' => 'text', 'content' => 'Opsi C', 'image_path' => null, 'is_correct' => false];

        $component->set('options', $options)
            ->set('correctOptionIndex', 2)
            ->call('save', '<p>Soal bebas</p>')
            ->assertHasNoErrors();

        $fresh = $question->options()->orderBy('sort_order')->get();
        $this->assertSame($oldIds, $fresh->take(2)->pluck('id')->all(), 'existing options keep their ids');
        $this->assertCount(3, $fresh);
        $this->assertSame('C', $question->fresh()->correctOption()->label);
    }

    public function test_option_chosen_by_a_participant_cannot_be_removed(): void
    {
        [, $attempt] = $this->startAttempt();
        $question = $attempt->answers()->first()->question;
        // Third option so removing one still leaves the 2 the form requires.
        $lastOption = $question->options()->create([
            'label' => 'C', 'content_type' => QuestionOptionContentType::Text,
            'content' => 'Opsi C', 'is_correct' => false, 'sort_order' => 3,
        ]);
        app(SkbExamService::class)->saveAnswer($attempt, $question->id, $lastOption->id);

        $component = Livewire::actingAs($this->admin)
            ->test(SoalIndex::class, ['jabatanSkb' => $this->jabatan])
            ->call('openEditModal', $question->id);
        $options = $component->get('options');
        array_pop($options);

        $component->set('options', $options)
            ->set('correctOptionIndex', 0)
            ->call('save', '<p>Opsi dikurangi</p>')
            ->assertHasErrors('options');

        $this->assertNotNull($lastOption->fresh(), 'chosen option must not be deleted');
        $this->assertSame($lastOption->id, SkbExamAnswer::query()->where('skb_exam_attempt_id', $attempt->id)->where('skb_question_id', $question->id)->value('selected_option_id'));
    }

    /** @return array{0: User, 1: SkbExamAttempt, 2: Event, 3: EventSession} */
    private function startAttempt(): array
    {
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $this->jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);

        for ($i = 1; $i <= 3; $i++) {
            $this->makeQuestion("Soal {$i}");
        }

        $event = Event::query()->create([
            'name' => 'SKB Integritas', 'exam_id' => null, 'status' => EventStatus::Active, 'created_by' => $this->admin->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);
        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skb_pin' => '2222', 'status' => EventStatus::Active,
        ]);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Peserta', 'nik' => '3500000000000002', 'jabatan_label' => 'Analis', 'jabatan_skb_id' => $this->jabatan->id,
        ]);

        return [$user, app(SkbExamService::class)->startAttempt($event, $participant), $event, $session];
    }

    private function makeQuestion(string $content): SkbQuestion
    {
        $this->admin ??= User::factory()->create(['role' => UserRole::Admin]);

        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $this->jabatan->id, 'content' => "<p>{$content}</p>",
            'difficulty' => 'medium', 'is_active' => true, 'created_by' => $this->admin->id,
        ]);

        foreach (['A', 'B'] as $order => $label) {
            $question->options()->create([
                'label' => $label, 'content_type' => QuestionOptionContentType::Text,
                'content' => "Opsi {$label}", 'is_correct' => $label === 'A', 'sort_order' => $order + 1,
            ]);
        }

        return $question;
    }
}
