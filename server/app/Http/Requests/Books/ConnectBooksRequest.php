<?php

declare(strict_types=1);

namespace App\Http\Requests\Books;

use Illuminate\Foundation\Http\FormRequest;

/**
 * invoiceNinja.connect and admin.books.connect: an Invoice Ninja token, and a selfhosted install's address.
 */
final class ConnectBooksRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:10', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
