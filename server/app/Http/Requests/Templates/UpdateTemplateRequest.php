<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.update: who may open it, and the library opt-in.
 */
final class UpdateTemplateRequest extends FormRequest
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
            'visibility' => ['sometimes', 'string', 'in:private,unlisted'],
            'libraryOptIn' => ['sometimes', 'boolean'],
        ];
    }
}
