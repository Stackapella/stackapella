<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.people.refund: how much of the latest Plus payment, and why.
 */
final class RefundPlusRequest extends FormRequest
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
            'amountCents' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }
}
