<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Support\Templates\Imported;
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
            // Made from sheet music the singer brought: they confirm they have the rights to it.
            'source' => ['nullable', 'string', 'in:'.Imported::SOURCE],
            'rightsConfirmed' => ['required_if:source,'.Imported::SOURCE, 'accepted_if:source,'.Imported::SOURCE],
            // "Make a copy": the template it copies, so a copy of an imported song stays one.
            'copiedFrom' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * The template exactly as sent, read from the body itself: the API's string cleaning (empty
     * strings to null, trimmed) would refuse an empty description or credit and change a lyric's
     * spaces. The rest of the request is cleaned as usual.
     *
     * @return array<string, mixed>
     */
    public function template(): array
    {
        $body = json_decode($this->getContent(), true);
        $template = is_array($body) ? ($body['template'] ?? null) : null;

        return is_array($template) ? $template : (array) $this->validated('template');
    }
}
