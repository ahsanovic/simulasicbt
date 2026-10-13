<?php

namespace App\Services\ModeUjianRoom;

use App\Enums\QuestionOptionContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything the browser exam room needs, sent once when the room opens:
 * every question of the attempt with its options and the saved answers.
 * Never the answer key: no is_correct, option scores or explanations.
 */
final class RoomPayload
{
    /**
     * @return array{kind: string, attemptId: int, deviceToken: string, title: string, versionBase: int, items: list<array<string, mixed>>}
     */
    public function build(RoomKind $kind, Model $attempt, string $deviceToken): array
    {
        $questionColumn = $kind->answerQuestionColumn();

        $answers = $kind->answerModel()::query()
            ->where($kind->answerAttemptColumn(), $attempt->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', $questionColumn, 'selected_option_id', 'is_marked', 'answer_version']);

        $questions = $kind->questions($answers->pluck($questionColumn)->map(fn ($id) => (int) $id)->all());

        $items = $answers->values()->map(function (Model $answer, int $index) use ($kind, $questions, $questionColumn) {
            $question = $questions[(int) $answer->{$questionColumn}] ?? null;

            return [
                'id' => (int) $answer->id,
                'number' => $index + 1,
                'badge' => $question !== null ? $kind->badge($question) : null,
                'content' => (string) html_for_display($question?->content),
                'options' => $question === null ? [] : $question->options->map(function (Model $option) {
                    $isImage = $option->content_type === QuestionOptionContentType::Image;

                    return [
                        'id' => (int) $option->id,
                        'label' => (string) $option->label,
                        'image' => $isImage ? storage_asset($option->image_path) : null,
                        'content' => $isImage ? null : (string) $option->content,
                    ];
                })->values()->all(),
                'option_id' => $answer->selected_option_id === null ? null : (int) $answer->selected_option_id,
                'marked' => (bool) $answer->is_marked,
                'version' => (int) $answer->answer_version,
            ];
        })->all();

        return [
            'kind' => $kind->key(),
            'attemptId' => (int) $attempt->getKey(),
            'deviceToken' => $deviceToken,
            'title' => $kind->title($attempt),
            'versionBase' => (int) $answers->max('answer_version'),
            'items' => $items,
        ];
    }
}
