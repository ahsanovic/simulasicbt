<?php

namespace Tests\Unit;

use App\Enums\SubjectCode;
use App\Services\GeneratedQuestionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneratedQuestionValidatorTest extends TestCase
{
    private GeneratedQuestionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new GeneratedQuestionValidator;
    }

    public function test_twk_requires_correct_option_index(): void
    {
        $question = $this->baseQuestion(correctIndex: -1);

        $this->assertSame(
            'Jawaban benar wajib dipilih.',
            $this->validator->validate($question, SubjectCode::Twk),
        );
    }

    public function test_twk_valid_question_passes(): void
    {
        $question = $this->baseQuestion(correctIndex: 0);

        $this->assertNull($this->validator->validate($question, SubjectCode::Tiu));
    }

    public function test_tkp_requires_unique_weights_one_to_five(): void
    {
        $question = $this->baseQuestion(isTkp: true);
        $question['options'][0]['score_weight'] = 5;
        $question['options'][1]['score_weight'] = 5;

        $this->assertSame(
            'Pada soal TKP, bobot setiap opsi tidak boleh duplikat.',
            $this->validator->validate($question, SubjectCode::Tkp),
        );
    }

    #[DataProvider('tkpValidWeightsProvider')]
    public function test_tkp_valid_weights_pass(array $weights): void
    {
        $question = $this->baseQuestion(isTkp: true);

        foreach ($weights as $index => $weight) {
            $question['options'][$index]['score_weight'] = $weight;
        }

        $this->assertNull($this->validator->validate($question, SubjectCode::Tkp));
    }

    public static function tkpValidWeightsProvider(): array
    {
        return [
            'ascending' => [[1, 2, 3, 4, 5]],
            'descending' => [[5, 4, 3, 2, 1]],
            'mixed' => [[3, 5, 1, 4, 2]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseQuestion(int $correctIndex = 0, bool $isTkp = false): array
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $options = [];

        foreach ($labels as $index => $label) {
            $options[] = [
                'label' => $label,
                'content' => "Pilihan {$label}",
                'is_correct' => ! $isTkp && $index === $correctIndex,
                'score_weight' => $isTkp ? ($index + 1) : null,
            ];
        }

        return [
            'content' => 'Contoh soal ujian',
            'explanation' => 'Penjelasan jawaban yang benar.',
            'options' => $options,
            'correct_option_index' => $correctIndex,
        ];
    }
}
