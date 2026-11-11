<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.check: a draft song, as templates.save takes it.
 */
final class CheckTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'array'],
        ];
    }
}
