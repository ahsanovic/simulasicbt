<?php

namespace App\Support;

use App\Models\Material;
use App\Models\MaterialGroup;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SkbQuestion;
use App\Models\SkbQuestionOption;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Question content shown in the exam rooms (text, options, subject/material).
 *
 * It does not change during an exam, but was loaded from 4 tables on every
 * "Simpan & Lanjutkan" of every participant. Cached per question instead;
 * editing a question or its options in the admin forgets the entry (model
 * events on Question/QuestionOption/SkbQuestion/SkbQuestionOption), and the
 * TTL bounds anything else (e.g. a renamed material). Answers and scores
 * never come from here — only what is displayed.
 *
 * Only raw attribute arrays go into the cache, and the models are rebuilt on
 * read: the cache refuses to unserialize PHP objects (config cache
 * serializable_classes = false, a guard against gadget-chain attacks).
 *
 * withTrashed: a question removed from the bank after an attempt started is
 * still part of that attempt, so it keeps showing instead of breaking the room.
 */
final class ExamQuestionCache
{
    public const TTL_SECONDS = 1800;

    public static function skd(int $questionId): ?Question
    {
        $data = Cache::remember(self::skdKey($questionId), self::TTL_SECONDS, function () use ($questionId) {
            $question = Question::withTrashed()
                ->with(['options', 'subject', 'material.materialGroup'])
                ->find($questionId);

            return $question === null ? false : [
                'question' => $question->getAttributes(),
                'options' => $question->options->map(fn (QuestionOption $option) => $option->getAttributes())->all(),
                'subject' => $question->subject?->getAttributes(),
                'material' => $question->material?->getAttributes(),
                'material_group' => $question->material?->materialGroup?->getAttributes(),
            ];
        });

        if (! is_array($data)) {
            return null;
        }

        $question = self::model(Question::class, $data['question']);
        $question->setRelation('options', self::models(QuestionOption::class, $data['options']));
        $question->setRelation('subject', $data['subject'] ? self::model(Subject::class, $data['subject']) : null);

        $material = $data['material'] ? self::model(Material::class, $data['material']) : null;
        $material?->setRelation('materialGroup', $data['material_group'] ? self::model(MaterialGroup::class, $data['material_group']) : null);
        $question->setRelation('material', $material);

        return $question;
    }

    public static function skb(int $questionId): ?SkbQuestion
    {
        $data = Cache::remember(self::skbKey($questionId), self::TTL_SECONDS, function () use ($questionId) {
            $question = SkbQuestion::withTrashed()->with('options')->find($questionId);

            return $question === null ? false : [
                'question' => $question->getAttributes(),
                'options' => $question->options->map(fn (SkbQuestionOption $option) => $option->getAttributes())->all(),
            ];
        });

        if (! is_array($data)) {
            return null;
        }

        $question = self::model(SkbQuestion::class, $data['question']);
        $question->setRelation('options', self::models(SkbQuestionOption::class, $data['options']));

        return $question;
    }

    public static function forgetSkd(?int $questionId): void
    {
        if ($questionId !== null) {
            Cache::forget(self::skdKey($questionId));
        }
    }

    public static function forgetSkb(?int $questionId): void
    {
        if ($questionId !== null) {
            Cache::forget(self::skbKey($questionId));
        }
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private static function model(string $class, array $attributes): Model
    {
        return (new $class)->newFromBuilder($attributes);
    }

    private static function models(string $class, array $rows)
    {
        return (new $class)->newCollection(array_map(fn (array $attributes) => self::model($class, $attributes), $rows));
    }

    private static function skdKey(int $questionId): string
    {
        return 'exam-question:skd:'.$questionId;
    }

    private static function skbKey(int $questionId): string
    {
        return 'exam-question:skb:'.$questionId;
    }
}
