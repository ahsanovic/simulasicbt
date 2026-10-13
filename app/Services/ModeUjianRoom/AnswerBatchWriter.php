<?php

namespace App\Services\ModeUjianRoom;

use App\Enums\ExamAttemptStatus;
use App\Support\ExamDeadline;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Saves answers sent by the browser exam room. An answer is written only when
 * its version (the browser's ever-increasing X-Exam-Seq counter) is newer than
 * the stored one, so a late, older request never overwrites a newer pick.
 */
final class AnswerBatchWriter
{
    /**
     * @param  list<array{id: int, option_id: ?int, is_marked: bool, version: int}>  $answers
     * @return array{saved: list<int>, stale: list<int>}
     *
     * @throws RoomClosed
     * @throws DeviceReplaced
     * @throws ValidationException
     */
    public function write(RoomKind $kind, Model $attempt, string $deviceToken, array $answers): array
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress || ! ExamDeadline::acceptsAnswers($attempt->expires_at)) {
            throw new RoomClosed;
        }

        if (! DeviceToken::matches($attempt, $deviceToken)) {
            throw new DeviceReplaced;
        }

        // The same answer twice in one batch: its newest version wins.
        $latest = [];

        foreach ($answers as $answer) {
            if (! isset($latest[$answer['id']]) || $answer['version'] > $latest[$answer['id']]['version']) {
                $latest[$answer['id']] = $answer;
            }
        }

        if ($latest === []) {
            return ['saved' => [], 'stale' => []];
        }

        $questionColumn = $kind->answerQuestionColumn();
        $stored = $kind->answerModel()::query()
            ->where($kind->answerAttemptColumn(), $attempt->getKey())
            ->whereKey(array_keys($latest))
            ->get(['id', $questionColumn, 'answer_version'])
            ->keyBy('id');

        if ($stored->count() !== count($latest)) {
            throw ValidationException::withMessages(['answers' => 'Jawaban tidak termasuk ujian ini.']);
        }

        $questions = $kind->questions($stored->pluck($questionColumn)->map(fn ($id) => (int) $id)->unique()->values()->all());

        foreach ($latest as $id => $answer) {
            $question = $questions[(int) $stored[$id]->{$questionColumn}] ?? null;

            if ($answer['option_id'] !== null && ($question === null || ! $question->options->contains('id', $answer['option_id']))) {
                throw ValidationException::withMessages(['answers' => 'Pilihan jawaban tidak sesuai soal.']);
            }
        }

        $saved = [];
        $stale = [];
        $fresh = [];

        foreach ($latest as $id => $answer) {
            $storedVersion = (int) $stored[$id]->answer_version;

            // Same version: a retry of a save whose response was lost. It is
            // already stored, so it counts as saved (not as a newer answer
            // from elsewhere, which would reload the room).
            if ($answer['version'] === $storedVersion) {
                $saved[] = $id;
            } elseif ($answer['version'] < $storedVersion) {
                $stale[] = $id;
            } else {
                $fresh[$id] = $answer;
            }
        }

        if ($fresh !== [] && $this->writeAll($kind, $attempt, $fresh) === count($fresh)) {
            array_push($saved, ...array_keys($fresh));
        } elseif ($fresh !== []) {
            // A write landed in between: only the rows now holding our version are ours.
            $versions = $kind->answerModel()::query()->whereKey(array_keys($fresh))->pluck('answer_version', 'id');

            foreach ($fresh as $id => $answer) {
                if ((int) ($versions[$id] ?? 0) === $answer['version']) {
                    $saved[] = $id;
                } else {
                    $stale[] = $id;
                }
            }
        }

        return ['saved' => $saved, 'stale' => $stale];
    }

    /**
     * The whole batch in one UPDATE: after an outage a browser sends dozens of
     * answers at once, and a whole room reconnects together. Each row keeps
     * its own version check in the WHERE, so an older answer never replaces a
     * newer one. answer_version is set last (MySQL applies SET left to right).
     *
     * @param  array<int, array{id: int, option_id: ?int, is_marked: bool, version: int}>  $answers  keyed by answer id
     * @return int rows written
     */
    private function writeAll(RoomKind $kind, Model $attempt, array $answers): int
    {
        $model = $kind->answerModel();
        $connection = (new $model)->getConnection();
        $grammar = $connection->getQueryGrammar();
        $dateFormat = $grammar->getDateFormat();

        $columns = [];

        foreach ($answers as $id => $answer) {
            $values = [
                ...$kind->answerValues($answer['option_id']),
                'is_marked' => $answer['is_marked'],
                'answer_version' => $answer['version'],
            ];

            foreach ($values as $column => $value) {
                $columns[$column][$id] = match (true) {
                    $value instanceof DateTimeInterface => $value->format($dateFormat),
                    is_bool($value) => (int) $value,
                    default => $value,
                };
            }
        }

        $sets = [];
        $bindings = [];

        foreach ($columns as $column => $valuesById) {
            $cases = '';

            foreach ($valuesById as $id => $value) {
                $cases .= ' when ? then ?';
                array_push($bindings, $id, $value);
            }

            $sets[] = $grammar->wrap($column).' = case '.$grammar->wrap('id').$cases.' end';
        }

        $sets[] = $grammar->wrap('updated_at').' = ?';
        $bindings[] = now()->format($dateFormat);
        $bindings[] = $attempt->getKey();

        $rows = [];

        foreach ($answers as $id => $answer) {
            $rows[] = '('.$grammar->wrap('id').' = ? and '.$grammar->wrap('answer_version').' < ?)';
            array_push($bindings, $id, $answer['version']);
        }

        return $connection->update(
            'update '.$grammar->wrapTable((new $model)->getTable()).' set '.implode(', ', $sets)
                .' where '.$grammar->wrap($kind->answerAttemptColumn()).' = ? and ('.implode(' or ', $rows).')',
            $bindings,
        );
    }
}
