<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.save: the whole template JSON (checked against the template format by the action),
 * and who may open it.
 */
final class SaveTemplateRequest extends FormRequest
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
            'template' => ['required', 'array'],
            'template.version' => ['required', 'integer', 'min:1', 'max:100000'],
            'template.title' => ['required', 'string', 'max:160'],
            'template.reviewed' => ['required', 'boolean'],
            'visibility' => ['nullable', 'string', 'in:private,unlisted'],
            'libraryOptIn' => ['nullable', 'boolean'],
            // A public template's new version (N3), and a remix's original.
            'declaration' => ['nullable', 'array:song,source,ownWork'],
            'declaration.song' => ['exclude_without:declaration', 'required', 'string', 'in:original,public_domain'],
            'declaration.source' => ['exclude_without:declaration', 'nullable', 'string', 'max:300'],
            'declaration.ownWork' => ['exclude_without:declaration', 'required', 'accepted'],
            'parentId' => ['nullable', 'string', 'max:80'],
        ];
    }
}
