<?php

declare(strict_types=1);

namespace App\Http\Requests\Books;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.books.plusInvoices.create: invoice an account for months of Plus.
 */
final class CreatePlusInvoiceRequest extends FormRequest
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
            'personId' => ['required', 'string', 'max:32'],
            'amountCents' => ['required', 'integer', 'between:100,10000000'],
            'currency' => ['required', 'in:usd,eur,gbp'],
            'months' => ['required', 'integer', 'between:1,36'],
        ];
    }
}
