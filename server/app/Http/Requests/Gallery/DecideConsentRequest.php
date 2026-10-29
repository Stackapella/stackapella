<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * consentRequests.decide: yes or no.
 */
final class DecideConsentRequest extends FormRequest
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
            'agree' => ['required', 'boolean'],
        ];
    }
}
