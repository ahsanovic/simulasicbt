<?php

namespace App\Services;

use App\Enums\ExamAttemptStatus;
use App\Enums\SubjectCode;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function startAttempt(Exam $exam, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user) {
            $generator = app(ExamQuestionGeneratorService::class);
            $difficulty = $exam->settings['difficulty'] ?? 'all';

            try {
                $generator->assertSufficientQuestions($difficulty);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'exam' => 'Bank soal tidak cukup untuk memulai ujian. Hubungi admin.',
                ]);
            }

            $attempt = ExamAttempt::query()->create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($exam->duration_minutes),
                'status' => ExamAttemptStatus::InProgress,
            ]);

            foreach ($generator->generate($difficulty) as $item) {
                ExamAnswer::query()->create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $item['id'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            return $attempt->load(['answers.question.options', 'answers.question.subject']);
        });
    }

    public function submitAttempt(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt->load(['answers.selectedOption', 'answers.question.subject']);

            $scores = [
                SubjectCode::Twk->value => 0,
                SubjectCode::Tiu->value => 0,
                SubjectCode::Tkp->value => 0,
            ];

            foreach ($attempt->answers as $answer) {
                if (! $answer->selected_option_id) {
                    continue;
                }

                $subjectCode = $answer->question->subject->code;
                $option = $answer->selectedOption;

                $scores[$subjectCode->value] += $subjectCode->pointsFromSelectedOption(
                    $option->score_weight,
                    $option->is_correct,
                );
            }

            $attempt->update([
                'status' => ExamAttemptStatus::Submitted,
                'submitted_at' => now(),
                'score_twk' => $scores[SubjectCode::Twk->value],
                'score_tiu' => $scores[SubjectCode::Tiu->value],
                'score_tkp' => $scores[SubjectCode::Tkp->value],
                'total_score' => array_sum($scores),
            ]);

            return $attempt->fresh();
        });
    }
}
