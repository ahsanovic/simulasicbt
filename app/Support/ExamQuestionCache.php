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

    public const WARM_TTL_SECONDS = 4 * 3600;

    private const SKD_RELATIONS = ['options', 'subject', 'material.materialGroup'];

    public static function skd(int $questionId): ?Question
    {
        $data = Cache::remember(self::skdKey($questionId), self::TTL_SECONDS, function () use ($questionId) {
            $question = Question::withTrashed()->with(self::SKD_RELATIONS)->find($questionId);

            return $question === null ? false : self::skdPayload($question);
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

            return $question === null ? false : self::skbPayload($question);
        });

        if (! is_array($data)) {
            return null;
        }

        $question = self::model(SkbQuestion::class, $data['question']);
        $question->setRelation('options', self::models(SkbQuestionOption::class, $data['options']));

        return $question;
    }

    /**
     * Fill the cache for every active question ahead of an exam, a chunk at a
     * time (a few queries per chunk instead of several per question), so the
     * first participants to reach a question do not all load it at once.
     * Kept for WARM_TTL_SECONDS, long enough to cover a whole exam.
     *
     * @return array{skd: int, skb: int}
     */
    public static function warm(int $chunk = 200): array
    {
        $counts = ['skd' => 0, 'skb' => 0];

        Question::query()->where('is_active', true)->with(self::SKD_RELATIONS)->chunkById($chunk, function ($questions) use (&$counts) {
            Cache::putMany($questions->mapWithKeys(fn (Question $q) => [self::skdKey($q->id) => self::skdPayload($q)])->all(), self::WARM_TTL_SECONDS);
            $counts['skd'] += $questions->count();
        });

        SkbQuestion::query()->where('is_active', true)->with('options')->chunkById($chunk, function ($questions) use (&$counts) {
            Cache::putMany($questions->mapWithKeys(fn (SkbQuestion $q) => [self::skbKey($q->id) => self::skbPayload($q)])->all(), self::WARM_TTL_SECONDS);
            $counts['skb'] += $questions->count();
        });

        return $counts;
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

    private static function skdPayload(Question $question): array
    {
        return [
            'question' => $question->getAttributes(),
            'options' => $question->options->map(fn (QuestionOption $option) => $option->getAttributes())->all(),
            'subject' => $question->subject?->getAttributes(),
            'material' => $question->material?->getAttributes(),
            'material_group' => $question->material?->materialGroup?->getAttributes(),
        ];
    }

    private static function skbPayload(SkbQuestion $question): array
    {
        return [
            'question' => $question->getAttributes(),
            'options' => $question->options->map(fn (SkbQuestionOption $option) => $option->getAttributes())->all(),
        ];
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
