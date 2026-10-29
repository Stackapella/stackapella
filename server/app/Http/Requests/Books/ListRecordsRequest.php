<?php

declare(strict_types=1);

namespace App\Http\Requests\Books;

use Illuminate\Foundation\Http\FormRequest;

/**
 * invoiceNinja.records.list and admin.books.records.list: which rows, and the next page.
 */
final class ListRecordsRequest extends FormRequest
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
            'status' => ['nullable', 'in:not_recorded,waiting,recorded,all'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
