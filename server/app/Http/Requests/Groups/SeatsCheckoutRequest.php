<?php

declare(strict_types=1);

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * groups.seats.checkout: how many seats (from 10, up to a group's members), in which currency.
 */
final class SeatsCheckoutRequest extends FormRequest
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
            'seats' => ['required', 'integer', 'min:'.(int) config('billing.choir.min_seats'), 'max:'.(int) config('usage.group_members')],
            'currency' => ['required', Rule::in(['usd', 'eur', 'gbp'])],
            // The EU, EEA and UK consent to start at once (billing.prices' startNowConsent), as for Plus.
            'startNowConsent' => ['nullable', 'boolean'],
        ];
    }
}
