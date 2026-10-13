<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body of the browser exam room's "jawaban" and "selesai" requests. */
class ModeUjianAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['skd', 'skb'])],
            'attempt_id' => ['required', 'integer'],
            'device_token' => ['required', 'string', 'size:40'],
            'answers' => ['present', 'array', 'max:250'],
            'answers.*.id' => ['required', 'integer'],
            'answers.*.option_id' => ['present', 'nullable', 'integer'],
            'answers.*.is_marked' => ['required', 'boolean'],
            'answers.*.version' => ['required', 'integer', 'min:1', 'max:4000000000'],
            'reason' => ['sometimes', Rule::in(['manual', 'time_up'])],
        ];
    }

    /** @return list<array{id: int, option_id: ?int, is_marked: bool, version: int}> */
    public function answers(): array
    {
        return array_map(fn (array $answer) => [
            'id' => (int) $answer['id'],
            'option_id' => $answer['option_id'] === null ? null : (int) $answer['option_id'],
            'is_marked' => (bool) $answer['is_marked'],
            'version' => (int) $answer['version'],
        ], array_values($this->validated('answers')));
    }
}
