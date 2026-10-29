<?php

declare(strict_types=1);

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/**
 * templates.publish: the licensing declaration.
 */
final class PublishTemplateRequest extends FormRequest
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
            'song' => ['required', 'string', 'in:original,public_domain'],
            'source' => ['nullable', 'string', 'max:300'],
            'ownWork' => ['required', 'accepted'],
        ];
    }
}
