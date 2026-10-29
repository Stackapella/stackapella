<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mudeo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.mudeo.contacts: an address, or part of one.
 */
final class SearchMudeoRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
