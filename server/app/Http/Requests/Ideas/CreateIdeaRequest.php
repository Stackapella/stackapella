<?php

declare(strict_types=1);

namespace App\Http\Requests\Ideas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ideas.create: the writer's template, a note, how many singers, and the rights.
 */
final class CreateIdeaRequest extends FormRequest
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
            'templateId' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:300'],
            'voicing' => ['nullable', 'integer', 'between:1,12'],
            'rightsConfirmed' => ['required', 'accepted'],
        ];
    }
}
