<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\People;

use App\Rules\ValidCursor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin.people.list: a search (part of an email, a handle or a name) and the cursor.
 */
final class ListPeopleRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'suspended' => ['nullable', 'boolean'],
            'adult' => ['nullable', 'boolean'],
            'country' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'plan' => ['nullable', 'in:plus,free'],
            'role' => ['nullable', 'in:singer,tester'],
            'sort' => ['nullable', 'in:newest,active'],
            'cursor' => ['nullable', 'string', 'max:500', new ValidCursor],
        ];
    }
}
