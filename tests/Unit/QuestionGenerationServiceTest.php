<?php

namespace Tests\Unit;

use App\Enums\SubjectCode;
use App\Services\GeneratedQuestionValidator;
use App\Services\QuestionGenerationService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class QuestionGenerationServiceTest extends TestCase
{
    private QuestionGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new QuestionGenerationService(new GeneratedQuestionValidator);
    }

    #[DataProvider('correctAnswerFormatProvider')]
    public function test_normalize_question_marks_expected_option_correct(array $question, string $expectedContent): void
    {
        $normalized = $this->normalizeQuestion($question, SubjectCode::Twk);

        $correctIndex = (int) $normalized['correct_option_index'];
        $this->assertSame($expectedContent, $normalized['options'][$correctIndex]['content']);
        $this->assertTrue($normalized['options'][$correctIndex]['is_correct']);

        foreach ($normalized['options'] as $index => $option) {
            $this->assertSame(
                $index === $correctIndex,
                $option['is_correct'],
                "is_correct mismatch on option {$option['label']}",
            );
        }
    }

    public function test_normalize_question_reads_camel_case_is_correct(): void
    {
        $normalized = $this->normalizeQuestion([
            'content' => 'Soal',
            'explanation' => 'Penjelasan',
            'options' => [
                ['label' => 'A', 'content' => 'Salah', 'isCorrect' => false],
                ['label' => 'B', 'content' => 'Benar', 'isCorrect' => true],
                ['label' => 'C', 'content' => 'Salah 2', 'isCorrect' => false],
                ['label' => 'D', 'content' => 'Salah 3', 'isCorrect' => false],
                ['label' => 'E', 'content' => 'Salah 4', 'isCorrect' => false],
            ],
        ], SubjectCode::Tiu);

        $correctIndex = (int) $normalized['correct_option_index'];
        $this->assertSame('Benar', $normalized['options'][$correctIndex]['content']);
    }

    public function test_normalize_question_reads_question_level_correct_answer(): void
    {
        $normalized = $this->normalizeQuestion([
            'content' => 'Soal',
            'explanation' => 'Penjelasan',
            'correct_answer' => 'D',
            'options' => [
                ['label' => 'A', 'content' => 'A'],
                ['label' => 'B', 'content' => 'B'],
                ['label' => 'C', 'content' => 'C'],
                ['label' => 'D', 'content' => 'Jawaban benar'],
                ['label' => 'E', 'content' => 'E'],
            ],
        ], SubjectCode::Twk);

        $correctIndex = (int) $normalized['correct_option_index'];
        $this->assertSame('Jawaban benar', $normalized['options'][$correctIndex]['content']);
    }

    public static function correctAnswerFormatProvider(): array
    {
        return [
            'snake_case_is_correct' => [
                [
                    'content' => 'Soal',
                    'explanation' => 'Penjelasan',
                    'options' => [
                        ['label' => 'A', 'content' => 'A', 'is_correct' => false],
                        ['label' => 'B', 'content' => 'B', 'is_correct' => false],
                        ['label' => 'C', 'content' => 'Jawaban benar', 'is_correct' => true],
                        ['label' => 'D', 'content' => 'D', 'is_correct' => false],
                        ['label' => 'E', 'content' => 'E', 'is_correct' => false],
                    ],
                ],
                'Jawaban benar',
            ],
            'string_boolean_false_does_not_mark_wrong_options_correct' => [
                [
                    'content' => 'Soal',
                    'explanation' => 'Penjelasan',
                    'options' => [
                        ['label' => 'A', 'content' => 'A', 'is_correct' => 'false'],
                        ['label' => 'B', 'content' => 'B', 'is_correct' => 'false'],
                        ['label' => 'C', 'content' => 'Jawaban benar', 'is_correct' => 'true'],
                        ['label' => 'D', 'content' => 'D', 'is_correct' => 'false'],
                        ['label' => 'E', 'content' => 'E', 'is_correct' => 'false'],
                    ],
                ],
                'Jawaban benar',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function normalizeQuestion(array $question, SubjectCode $subjectCode): array
    {
        $method = new ReflectionMethod(QuestionGenerationService::class, 'normalizeQuestion');
        $method->setAccessible(true);

        return $method->invoke($this->service, $question, $subjectCode, 'medium');
    }
}
