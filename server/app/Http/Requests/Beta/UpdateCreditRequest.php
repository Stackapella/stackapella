<?php

declare(strict_types=1);

namespace App\Http\Requests\Beta;

use App\Enums\CreditMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * beta.credit.update: the credit, changed at any time.
 */
final class UpdateCreditRequest extends FormRequest
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
            'creditMode' => ['required_without:creditLinksProfile', Rule::enum(CreditMode::class)],
            // Their credit links to their public profile, when it can (BetaSinger::creditHandle).
            'creditLinksProfile' => ['sometimes', 'boolean'],
        ];
    }
}
