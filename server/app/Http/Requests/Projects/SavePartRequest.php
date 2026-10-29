<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use App\Models\Part;
use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * parts.save (contract/api.md, "Projects and cloud save").
 */
final class SavePartRequest extends FormRequest
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
            'changeId' => ['required', 'uuid'],
            'projectId' => ['required', 'uuid'],
            'version' => ['required', 'integer', 'min:0'],
            'order' => ['required', 'integer', 'between:0,1000'],
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'volume' => ['required', 'numeric', 'between:0,2'],
            'pan' => ['required', 'numeric', 'between:-1,1'],
            'muted' => ['required', 'boolean'],
            'solo' => ['required', 'boolean'],
            'nudgeFrames' => ['required', 'integer', 'between:-100000000,100000000'],
            'trimStartFrames' => ['required', 'integer', 'between:0,1000000000'],
            'trimEndFrames' => ['required', 'integer', 'between:0,1000000000'],
            'templatePartKey' => ['present', 'nullable', 'string', 'max:40'],
            // Left out, the stored one stays (an older app never turns an instrument into a voice).
            'kind' => ['sometimes', 'string', Rule::in(Part::KINDS)],
            'currentTakeId' => ['present', 'nullable', 'uuid'],
            'settings' => ['present', 'array', new JsonSize(16)],
            'createdAt' => ['nullable', 'date'],
        ];
    }
}
