<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use App\Enums\CreditMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * beta.consent.create: the agreement accepted, 18 or over, and the credit.
 */
final class ConsentRequest extends FormRequest
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
            'creditMode' => ['required', Rule::enum(CreditMode::class)],
        ];
    }
}
