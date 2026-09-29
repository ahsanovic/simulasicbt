<?php

namespace Tests\Unit;

use App\Enums\QuestionOptionContentType;
use App\Models\JabatanSkb;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkbQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_jabatan_skb_has_many_questions(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis Kebijakan',
            'slug' => 'analis-kebijakan',
            'is_active' => true,
        ]);

        SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal 1</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        $this->assertCount(1, $jabatan->fresh()->questions);
    }

    public function test_options_are_ordered_by_sort_order(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Auditor',
            'slug' => 'auditor',
            'is_active' => true,
        ]);

        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal urutan</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => false,
            'sort_order' => 2,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $labels = $question->fresh()->options->pluck('label')->all();

        $this->assertSame(['A', 'B'], $labels);
    }

    public function test_correct_option_returns_the_flagged_option(): void
    {
        $jabatan = JabatanSkb::query()->create([
            'name' => 'Pustakawan',
            'slug' => 'pustakawan',
            'is_active' => true,
        ]);

        $question = SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatan->id,
            'content' => '<p>Soal benar</p>',
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'A',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan A',
            'is_correct' => false,
            'sort_order' => 1,
        ]);

        $correct = SkbQuestionOption::query()->create([
            'skb_question_id' => $question->id,
            'label' => 'B',
            'content_type' => QuestionOptionContentType::Text,
            'content' => 'Pilihan B',
            'is_correct' => true,
            'sort_order' => 2,
        ]);

        $this->assertSame($correct->id, $question->fresh()->correctOption()->id);
    }
}
