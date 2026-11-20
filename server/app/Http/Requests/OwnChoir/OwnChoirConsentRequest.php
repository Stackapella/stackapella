<?php

declare(strict_types=1);

namespace App\Http\Requests\OwnChoir;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ownChoir.consent: the terms' version shown, and 18 or over.
 */
final class OwnChoirConsentRequest extends FormRequest
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
        ];
    }
}
