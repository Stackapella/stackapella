<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * billing.checkout.
 */
final class CheckoutRequest extends FormRequest
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
            'interval' => ['required', Rule::in(['month', 'year'])],
            'currency' => ['required', Rule::in(['usd', 'eur', 'gbp'])],
            'trial' => ['required', 'boolean'],
            'moment' => ['nullable', 'string', 'max:40'],
            // The EU, EEA and UK consent to start at once (billing.prices' startNowConsent).
            'startNowConsent' => ['nullable', 'boolean'],
        ];
    }
}
