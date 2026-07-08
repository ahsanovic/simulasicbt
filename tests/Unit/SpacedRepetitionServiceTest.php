<?php

namespace Tests\Unit;

use App\Enums\FlashcardRating;
use App\Models\Flashcard;
use App\Models\User;
use App\Services\SpacedRepetitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpacedRepetitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_rating_resets_interval_and_increments_forget_count(): void
    {
        $user = User::factory()->create();
        $card = Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => 'question',
            'source_id' => 1,
            'front' => 'Pertanyaan',
            'back' => 'Jawaban',
            'subject_code' => 'twk',
            'interval_days' => 14,
            'repetition_count' => 3,
            'forget_count' => 1,
            'next_review_at' => now()->subDay(),
        ]);

        app(SpacedRepetitionService::class)->applyRating($card, FlashcardRating::Forgot);

        $card->refresh();

        $this->assertSame(2, $card->forget_count);
        $this->assertSame(0, $card->repetition_count);
        $this->assertSame(1, $card->interval_days);
        $this->assertTrue($card->next_review_at->isFuture());
    }

    public function test_remembered_rating_advances_through_interval_ladder(): void
    {
        $user = User::factory()->create();
        $card = Flashcard::query()->create([
            'user_id' => $user->id,
            'source_type' => 'question',
            'source_id' => 2,
            'front' => 'Pertanyaan',
            'back' => 'Jawaban',
            'subject_code' => 'twk',
            'interval_days' => 1,
            'repetition_count' => 0,
            'forget_count' => 0,
            'next_review_at' => now()->subDay(),
        ]);

        $service = app(SpacedRepetitionService::class);

        $service->applyRating($card, FlashcardRating::Remembered);
        $card->refresh();
        $this->assertSame(1, $card->repetition_count);
        $this->assertSame(1, $card->interval_days);

        $service->applyRating($card, FlashcardRating::Remembered);
        $card->refresh();
        $this->assertSame(2, $card->repetition_count);
        $this->assertSame(3, $card->interval_days);

        $service->applyRating($card, FlashcardRating::Remembered);
        $card->refresh();
        $this->assertSame(3, $card->repetition_count);
        $this->assertSame(7, $card->interval_days);
    }
}
