<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use Illuminate\Foundation\Http\FormRequest;

/**
 * reviews.save: the coach's whole draft (kept as plain text within its sizes).
 */
final class ReviewDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'comments' => ['present', 'array', 'max:100'],
            'comments.*.id' => ['nullable', 'uuid'],
            'comments.*.atSeconds' => ['required', 'numeric', 'min:0', 'max:3600'],
            'comments.*.noteIndex' => ['nullable', 'integer', 'min:0'],
            'comments.*.text' => ['nullable', 'string', 'max:1000'],
            'comments.*.mediaId' => ['nullable', 'uuid'],
            'summary' => ['nullable', 'array'],
            'summary.working' => ['nullable', 'string', 'max:1000'],
            'summary.toWorkOn' => ['nullable', 'array', 'max:3'],
            'summary.toWorkOn.*' => ['string', 'max:300'],
            'summary.practise' => ['nullable', 'string', 'max:1000'],
            'exercises' => ['present', 'array', 'max:20'],
            'exercises.*' => ['array:packId,exerciseId'],
            'exercises.*.packId' => ['nullable', 'uuid'],
            'exercises.*.exerciseId' => ['required', 'string', 'max:80'],
            'demoMediaId' => ['nullable', 'uuid'],
        ];
    }
}
