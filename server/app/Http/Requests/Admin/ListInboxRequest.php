<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.support.list and admin.appeals.list: which ones, and the next page.
 */
final class ListInboxRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:20'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }
}
