<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * beta.aiLicence.update: the credit choice.
 */
final class UpdateAiLicenceRequest extends FormRequest
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
            'credited' => ['required', 'boolean'],
        ];
    }
}
