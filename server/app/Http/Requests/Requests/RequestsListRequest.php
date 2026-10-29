<?php

declare(strict_types=1);

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * requests.list: words like a title, a status, an order, a page.
 */
final class RequestsListRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'in:requested,checking,arranging,ready,cant'],
            'sort' => ['sometimes', 'nullable', 'string', 'in:wanted,newest'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
