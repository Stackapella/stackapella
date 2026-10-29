<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use App\Rules\JsonSize;
use Illuminate\Foundation\Http\FormRequest;

/**
 * projects.save (contract/api.md, "Projects and cloud save").
 */
final class SaveProjectRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:0'],
            // Blank until the person names it in the studio (Hillel: a new project opens at once).
            'title' => ['present', 'nullable', 'string', 'max:120'],
            'bpm' => ['required', 'numeric', 'between:20,300'],
            'key' => ['present', 'nullable', 'string', 'max:8', 'regex:/^[A-G](#|b)?m?$/'],
            'timeSignature' => ['required', 'string', 'regex:/^(1[0-6]|[1-9])\/(1|2|4|8|16)$/'],
            'template' => ['present', 'nullable', 'array:id,version'],
            'template.id' => ['required_with:template', 'string', 'max:80'],
            'template.version' => ['required_with:template', 'integer', 'min:1'],
            'settings' => ['present', 'array', new JsonSize(256)],
            'createdAt' => ['nullable', 'date'],
        ];
    }
}
