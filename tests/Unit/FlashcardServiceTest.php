<?php

namespace Tests\Unit;

use App\Enums\FlashcardSourceType;
use App\Enums\SubjectCode;
use App\Livewire\Peserta\KartuSakti;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\FlashcardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlashcardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_from_question_pairs_front_and_back_from_same_question(): void
    {
        $user = User::factory()->create();
        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);
        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'pancasila',
            'name' => 'Pancasila',
            'sort_order' => 1,
        ]);
        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'material_id' => $material->id,
            'content' => '<p>Apa sila pertama Pancasila?</p>',
            'explanation' => '<p>Sila pertama adalah Ketuhanan Yang Maha Esa.</p>',
            'difficulty' => 'easy',
            'is_active' => true,
        ]);
        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'content' => 'Ketuhanan Yang Maha Esa',
            'is_correct' => true,
            'sort_order' => 1,
        ]);
        QuestionOption::query()->create([
            'question_id' => $question->id,
            'label' => 'B',
            'content' => 'Kemanusiaan yang adil',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        $card = app(FlashcardService::class)->saveFromQuestion($user, $question);

        $this->assertSame($question->id, $card->source_id);
        $this->assertSame($question->content, $card->front);
        $this->assertStringContainsString('Ketuhanan Yang Maha Esa', $card->back);
        $this->assertStringContainsString('Sila pertama adalah Ketuhanan Yang Maha Esa.', $card->back);
    }

    public function test_kartu_sakti_review_uses_locked_payload_for_front_and_back(): void
    {
        $user = User::factory()->create();

        Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => 1,
            'front' => '<p>Soal A</p>',
            'back' => '<p>Jawaban A</p>',
            'subject_code' => SubjectCode::Twk,
            'interval_days' => 1,
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now()->subHour(),
        ]);

        Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => FlashcardSourceType::Question,
            'source_id' => 2,
            'front' => '<p>Soal B</p>',
            'back' => '<p>Jawaban B</p>',
            'subject_code' => SubjectCode::Tiu,
            'interval_days' => 1,
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now()->subMinutes(30),
        ]);

        $component = Livewire::actingAs($user)
            ->test(KartuSakti::class)
            ->call('startReview');

        $payload = $component->get('cardsPayload');

        $this->assertCount(2, $payload);
        $this->assertStringContainsString('Soal A', $payload[0]['front_html']);
        $this->assertStringContainsString('Jawaban A', $payload[0]['back_html']);
        $this->assertStringContainsString('Soal B', $payload[1]['front_html']);
        $this->assertStringContainsString('Jawaban B', $payload[1]['back_html']);
    }
}
