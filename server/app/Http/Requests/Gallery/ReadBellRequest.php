<?php

declare(strict_types=1);

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * notifications.read: everything, or up to one item.
 */
final class ReadBellRequest extends FormRequest
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
            'all' => ['sometimes', 'boolean'],
            'upTo' => ['required_without:all', 'nullable', 'string', 'max:32'],
        ];
    }
}
