<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.show: an older version, for a project made on it.
 */
final class ShowTemplateRequest extends FormRequest
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
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
