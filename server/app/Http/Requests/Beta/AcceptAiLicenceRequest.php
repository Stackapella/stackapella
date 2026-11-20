<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * beta.aiLicence.accept: the version shown, 18 or over, and the credit choice.
 */
final class AcceptAiLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['adult.accepted' => __('validation.adult'), 'adult.required' => __('validation.adult')];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer'],
            'adult' => ['required', 'accepted'],
            'credited' => ['required', 'boolean'],
        ];
    }
}
