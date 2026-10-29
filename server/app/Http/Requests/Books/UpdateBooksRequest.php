<?php

declare(strict_types=1);

namespace App\Http\Requests\Books;

use Illuminate\Foundation\Http\FormRequest;

/**
 * invoiceNinja.connection.update: whether singers also get Invoice Ninja's invoice by email.
 */
final class UpdateBooksRequest extends FormRequest
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
            'sendEmail' => ['required', 'boolean'],
        ];
    }
}
