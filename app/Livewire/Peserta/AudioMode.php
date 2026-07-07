<?php

namespace App\Livewire\Peserta;

use App\Enums\SubjectCode;
use App\Models\Question;
use App\Services\AudioLearningService;
use App\Services\AudioModeQuestionGeneratorService;
use App\Services\GamificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Audio Mode')]
class AudioMode extends Component
{
    public string $mode = 'setup';

    public string $subjectCode = 'twk';

    public int $questionLimit = AudioModeQuestionGeneratorService::DEFAULT_LIMIT;

    /** @var list<int> */
    #[Locked]
    public array $questionIds = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $questionsPayload = [];

    public int $completedCount = 0;

    public int $sessionStartedAt = 0;

    public ?int $summaryDurationSeconds = null;

    public ?int $summaryXp = null;

    public int $dailyStreak = 0;

    public int $totalXp = 0;

    /** @var array<string, array{label: string, description: string}> */
    public const PACKAGES = [
        10 => [
            'label' => 'Paket Kilat',
            'description' => '10 soal — cocok untuk rehat kopi 5 menit',
        ],
        20 => [
            'label' => 'Paket Standar',
            'description' => '20 soal — target belajar realistis (default)',
        ],
        30 => [
            'label' => 'Paket Sedang',
            'description' => '30 soal — cocok saat perjalanan, ngopi di cafe, dan lainnya',
        ],
        50 => [
            'label' => 'Paket Puas',
            'description' => '50 soal — cocok saat bersantai, menjelang tidur, dan lainnya',
        ],
    ];

    public function mount(AudioLearningService $audioLearningService, GamificationService $gamificationService): void
    {
        $this->dailyStreak = $audioLearningService->dailyStreak(auth()->user());
        $this->totalXp = $gamificationService->totalXp(auth()->user());
    }

    public function startSession(AudioModeQuestionGeneratorService $generator): void
    {
        $this->validate([
            'subjectCode' => ['required', 'in:twk,tiu,tkp'],
            'questionLimit' => ['required', 'integer', 'in:10,20,30,50'],
        ]);

        $code = SubjectCode::from($this->subjectCode);

        try {
            $this->questionIds = $generator->generate($code, $this->questionLimit);
        } catch (ValidationException $exception) {
            $this->addError('subject', $exception->validator->errors()->first('subject'));

            return;
        }

        $questions = $generator->loadQuestions($this->questionIds);

        $this->questionsPayload = $questions
            ->values()
            ->map(fn (Question $question, int $index) => $this->buildQuestionPayload($question, $index))
            ->all();

        $this->completedCount = 0;
        $this->sessionStartedAt = now()->timestamp;
        $this->summaryDurationSeconds = null;
        $this->summaryXp = null;
        $this->mode = 'playing';
    }

    public function completeQuestion(int $index): void
    {
        if ($index === $this->completedCount) {
            $this->completedCount++;
        }
    }

    public function finishSession(AudioLearningService $audioLearningService, GamificationService $gamificationService): void
    {
        if ($this->mode !== 'playing') {
            return;
        }

        $duration = max(0, now()->timestamp - $this->sessionStartedAt);
        $completed = min($this->completedCount, count($this->questionIds));

        if ($completed > 0) {
            $audioLearningService->recordSession(
                auth()->user(),
                SubjectCode::from($this->subjectCode),
                $completed,
                $duration,
            );
        }

        $this->summaryDurationSeconds = $duration;
        $this->summaryXp = $completed;
        $this->dailyStreak = $audioLearningService->dailyStreak(auth()->user());
        $this->totalXp = $gamificationService->totalXp(auth()->user());
        $this->mode = 'finished';
    }

    public function backToSetup(): void
    {
        $this->mode = 'setup';
        $this->questionIds = [];
        $this->questionsPayload = [];
        $this->completedCount = 0;
        $this->sessionStartedAt = 0;
        $this->summaryDurationSeconds = null;
        $this->summaryXp = null;
    }

    public function getSubjectLabelProperty(): string
    {
        return SubjectCode::from($this->subjectCode)->label();
    }

    public function render(AudioModeQuestionGeneratorService $generator)
    {
        $availableCounts = collect(SubjectCode::cases())
            ->mapWithKeys(fn (SubjectCode $code) => [
                $code->value => $generator->availableCount($code),
            ]);

        return view('livewire.peserta.audio-mode', compact('availableCounts'))
            ->layout('layouts.peserta', [
                'activeNav' => 'audio',
                'showNav' => in_array($this->mode, ['setup', 'finished'], true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuestionPayload(Question $question, int $index): array
    {
        $options = $question->options->map(function ($option) use ($question) {
            return [
                'label' => $option->label,
                'text' => $option->isImage()
                    ? 'berupa gambar, lihat layar Anda'
                    : plain_text_for_tts($option->content),
                'is_image' => $option->isImage(),
                'image_url' => $option->imageUrl(),
                'is_correct' => $question->isKeyOption($option),
            ];
        });

        $correctOption = $options->firstWhere('is_correct', true);

        return [
            'id' => $question->id,
            'number' => $index + 1,
            'question' => plain_text_for_tts($question->content),
            'question_html' => html_for_display($question->content),
            'options' => $options->values()->all(),
            'correct_label' => $correctOption['label'] ?? '',
            'explanation' => plain_text_for_tts($question->explanation),
            'subject' => $question->subject->code->label(),
        ];
    }
}
